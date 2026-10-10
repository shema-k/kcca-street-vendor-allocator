<?php
/**
 * Thin PDO wrapper. Every query in the system goes through here.
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;
    private static int $queries = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        /* Connect over the socket when there is one: it is the reliable
           path for a locally bundled server, and it works even when the
           TCP port is blocked or not yet listening. */
        $dsn = DB_SOCKET !== '' && file_exists(DB_SOCKET)
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', DB_SOCKET, DB_NAME)
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<!doctype html><meta charset="utf-8"><title>Database unavailable</title>';
            echo '<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;background:#f4f5f7;color:#2b2b2b}'
               . 'main{max-width:640px;margin:12vh auto;background:#fff;padding:40px;border-radius:12px;'
               . 'box-shadow:0 4px 24px rgba(0,0,0,.08);border-top:6px solid #F12D24}'
               . 'h1{font-size:20px;margin:0 0 12px}code{background:#f4f5f7;padding:2px 6px;border-radius:4px}'
               . '</style><main><h1>Database connection failed</h1>';
            echo '<p>KCCA VRSP cannot reach its database. Confirm the server is running and that '
               . '<code>config/config.php</code> holds the right credentials.</p>';
            if (getenv('KCCA_DEBUG')) {
                echo '<pre style="font-size:12px;white-space:pre-wrap;color:#8a1c14">' .
                     htmlspecialchars($e->getMessage()) . '</pre>';
            }
            echo '</main>';
            exit;
        }

        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        self::$queries++;
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    /** @return array<int,mixed> */
    public static function column(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function insert(string $table, array $data): int
    {
        $data = array_map(
            static fn($v) => is_bool($v) ? ($v ? 1 : 0) : $v,
            $data
        );
        $cols = array_keys($data);
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn($c) => "`$c`", $cols)),
            implode(', ', array_map(static fn($c) => ':' . $c, $cols))
        );
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Bulk insert. Returns the number of rows written.
     * Used by the seeder, which writes hundreds of thousands of rows.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public static function insertMany(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $cols = array_keys($rows[0]);
        $colSql = implode(', ', array_map(static fn($c) => "`$c`", $cols));

        $tuple  = [];
        $params = [];

        foreach ($rows as $i => $row) {
            $marks = [];
            foreach ($cols as $col) {
                $key = "r{$i}_{$col}";
                $marks[] = ':' . $key;
                // Normalise: booleans must reach TINYINT as 0/1, and arrays
                // as JSON, or the driver binds '' and strict mode rejects it.
                $val = $row[$col] ?? null;
                $params[$key] = match (true) {
                    is_bool($val)  => $val ? 1 : 0,
                    is_array($val) => json_encode($val),
                    default        => $val,
                };
            }
            $tuple[] = '(' . implode(', ', $marks) . ')';
        }

        $sql = sprintf('INSERT INTO `%s` (%s) VALUES %s', $table, $colSql, implode(', ', $tuple));
        self::$queries++;
        self::pdo()->prepare($sql)->execute($params);

        return count($rows);
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = array_map(static fn($c) => "`$c` = :$c", array_keys($data));
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        return self::run($sql, array_merge($data, $whereParams))->rowCount();
    }

    /**
     * Run a callable inside a transaction.
     *
     * Nesting is supported via SAVEPOINT, because a service method called
     * from inside another service's transaction is a normal thing to do:
     * approving a stall request both opens a vendor record and writes an
     * allocation. Without nesting, that inner call fails with "a transaction
     * is already active" — a confusing failure for something that is a
     * perfectly legitimate call.
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();

        /* Outer transaction: BEGIN. Inner: SAVEPOINT, so a failure inside
           the inner block unwinds only its own work. */
        $isOuter = !$pdo->inTransaction();
        if ($isOuter) {
            $pdo->beginTransaction();
        } else {
            $savepoint = 'sp_' . str_replace('.', '', (string) mt_rand());
            $pdo->exec('SAVEPOINT ' . $savepoint);
        }

        try {
            $result = $fn($pdo);

            if ($isOuter) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }

            return $result;
        } catch (Throwable $e) {
            if ($isOuter) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } elseif ($pdo->inTransaction()) {
                /* Roll back to the savepoint but keep the outer transaction
                   alive, so the caller's transaction can still commit. */
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
                $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
            }
            throw $e;
        }
    }

    public static function queryCount(): int
    {
        return self::$queries;
    }
}
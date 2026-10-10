<?php
/**
 * POST /api/location_save.php
 *   action=save-restriction | delete-restriction | save-space
 *
 * Location and space management: the land-use restrictions that gate the
 * recommendation engine, and the individual pitches inside a market.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');

function refuse(string $message, int $code = 400): never
{
    jsonOut(['ok' => false, 'error' => $message], $code);
}

/* Every category code the restrictions may name. */
$categoryCodes = static function (): array {
    return Database::column('SELECT code FROM trade_categories ORDER BY code');
};

switch ($action) {

    /* ---------------- Land use restriction ---------------- */
    case 'save-restriction': {
        requirePerm('manage');

        $rules = [
            'market_id'    => ['type' => 'int', 'label' => 'Market', 'required' => true, 'min' => 1],
            'code'         => ['type' => 'text', 'label' => 'Restriction code', 'required' => true,
                                'max' => 40, 'pattern' => '/^[A-Za-z0-9\-]+$/',
                                'pattern_message' => 'Restriction codes may contain letters, numbers and hyphens only.'],
            'title'        => ['type' => 'text', 'label' => 'Title', 'required' => true, 'max' => 160],
            'description'  => ['type' => 'textarea', 'label' => 'Description', 'required' => true, 'max' => 2000],
            'excluded_categories' => ['type' => 'text', 'label' => 'Excluded trades', 'max' => 255],
            'applies_to_section'  => ['type' => 'text', 'label' => 'Section', 'max' => 80],
            'authority'    => ['type' => 'text', 'label' => 'Recorded by', 'max' => 160],
            'legal_basis'  => ['type' => 'text', 'label' => 'Legal basis', 'max' => 200],
            'effective_from' => ['type' => 'date', 'label' => 'Effective from', 'required' => true],
            'effective_to'   => ['type' => 'date', 'label' => 'Effective to'],
        ];

        $v = new Validator($_POST, $rules);
        $v->checkExists('market_id', 'markets', $_POST['market_id'] ?? null);

        /* An end date cannot precede the start date. */
        $from = $v->value('effective_from');
        $to   = $v->value('effective_to');
        if ($from && $to && strtotime((string) $to) < strtotime((string) $from)) {
            $v->errors()['effective_to'] = 'The end date cannot be before the start date.';
        }

        /* Any trade named must exist, or the gate silently does nothing. */
        $known = $categoryCodes();
        $named = array_filter(array_map('trim', explode(',', (string) ($_POST['excluded_categories'] ?? ''))));
        foreach ($named as $code) {
            if (!in_array($code, $known, true)) {
                $v->errors()['excluded_categories'] =
                    'Unrecognised trade code "' . $code . '". Leave it blank to restrict the whole site.';
                break;
            }
        }

        if ($v->fails()) {
            jsonOut([
                'ok'     => false,
                'error'  => 'The restriction could not be saved. Correct the highlighted fields.',
                'errors' => $v->errors(),
                'values' => $_POST,
            ], 422);
        }

        $data = $v->validated();
        $data['excluded_categories'] = implode(',', $named);
        $data['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        /* The code must be unique within a market. */
        $clash = Database::one(
            'SELECT id FROM land_use_restrictions WHERE market_id = ? AND code = ?',
            [(int) $data['market_id'], $data['code']]
        );
        if ($clash) {
            jsonOut([
                'ok'     => false,
                'error'  => 'That restriction code already exists at this market.',
                'errors' => ['code' => 'This code is already used at this market. Choose another.'],
            ], 409);
        }

        $id = Database::insert('land_use_restrictions', $data);

        Auth::log('save-restriction', 'land_use_restrictions', $id,
            $data['title'] . ' — ' . ($data['excluded_categories'] ?: 'whole site'));

        jsonOut(['ok' => true, 'id' => $id,
                 'message' => 'Restriction recorded. Recommendations will now respect it.']);
    }

    case 'delete-restriction': {
        requirePerm('manage');
        $id = (int) ($_POST['restriction_id'] ?? 0);
        $row = Database::one('SELECT * FROM land_use_restrictions WHERE id = ?', [$id]);
        if (!$row) {
            refuse('That restriction does not exist.', 404);
        }
        Database::run('DELETE FROM land_use_restrictions WHERE id = ?', [$id]);
        Auth::log('delete-restriction', 'land_use_restrictions', $id, $row['title'] . ' removed');
        jsonOut(['ok' => true, 'message' => 'Restriction removed.']);
    }

    /* ---------------- An individual pitch ---------------- */
    case 'save-space': {
        requirePerm('manage');

        $id = (int) ($_POST['space_id'] ?? 0);
        $space = Database::one('SELECT * FROM market_spaces WHERE id = ?', [$id]);
        if (!$space) {
            refuse('That pitch does not exist.', 404);
        }

        /* A pitch that is occupied cannot be edited into a different
           status; that would silently overwrite an allocation. */
        if ($space['status'] === 'occupied') {
            $live = Database::one(
                "SELECT reference FROM allocations
                  WHERE market_space_id = ? AND status IN ('accepted','occupied') LIMIT 1",
                [$id]
            );
            if ($live) {
                refuse('This pitch is occupied under allocation ' . $live['reference']
                     . '. Withdraw that allocation before changing the pitch.', 409);
            }
        }

        $v = new Validator($_POST, [
            'section'         => ['type' => 'text', 'label' => 'Section', 'max' => 80],
            'size_sqm'        => ['type' => 'decimal', 'label' => 'Size in square metres',
                                   'required' => true, 'min' => 0.5, 'max' => 500],
            'status'          => ['type' => 'enum', 'label' => 'Status', 'required' => true,
                                   'options' => ['vacant', 'occupied', 'reserved', 'under-maintenance']],
            'monthly_rent_ugx' => ['type' => 'money', 'label' => 'Monthly rent', 'min' => 0],
            'has_water'       => ['type' => 'checkbox', 'label' => 'Water'],
            'has_power'       => ['type' => 'checkbox', 'label' => 'Power'],
            'near_toilet'     => ['type' => 'checkbox', 'label' => 'Near toilet'],
            'step_free'       => ['type' => 'checkbox', 'label' => 'Step free'],
        ]);

        if ($v->fails()) {
            jsonOut([
                'ok'     => false,
                'error'  => 'The pitch could not be saved. Correct the highlighted fields.',
                'errors' => $v->errors(),
                'values' => $_POST,
            ], 422);
        }

        $data = $v->validated();
        Database::update('market_spaces', $data, 'id = :id', ['id' => $id]);

        /* Keep the market's vacancy count honest when a pitch is
           deliberately withdrawn for maintenance. */
        Database::run(
            "UPDATE markets m
                SET vacant_spaces = (
                    SELECT COUNT(*) FROM market_spaces s
                     WHERE s.market_id = m.id AND s.status = 'vacant')
              WHERE m.id = ?",
            [(int) $space['market_id']]
        );

        Auth::log('update-space', 'market_spaces', $id, $space['space_code'] . ' updated');

        jsonOut(['ok' => true, 'message' => sprintf('Pitch %s updated.', $space['space_code'])]);
    }

    default:
        refuse('Unknown action.', 400);
}
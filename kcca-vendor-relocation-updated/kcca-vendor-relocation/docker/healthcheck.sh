#!/bin/sh
#
# Liveness probe. The public portal renders straight from the database and
# needs no session, so a 200 here means Apache, PHP and MariaDB all work.
#
exec php -r '
    $ctx = stream_context_create(["http" => ["timeout" => 4, "ignore_errors" => true]]);
    $body = @file_get_contents("http://127.0.0.1/pages/portal.php", false, $ctx);
    if ($body === false) {
        fwrite(STDERR, "portal unreachable\n");
        exit(1);
    }
    $status = $http_response_header[0] ?? "";
    if (strpos($status, "200") === false) {
        fwrite(STDERR, "portal returned: {$status}\n");
        exit(1);
    }
    exit(0);
'

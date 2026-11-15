<?php
/**
 * POST /api/footfall_save.php
 *   action=record | delete
 *
 * Recording counted footfall. This is the input the reach model learns
 * from, so it is validated strictly: a band, a day type and a count are
 * all required, and a count of people cannot be negative.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
requireToken();

$action = (string) ($_POST['action'] ?? '');

$rules = [
    'market_id'      => ['type' => 'int', 'label' => 'Market', 'required' => true, 'min' => 1],
    'observed_on'    => ['type' => 'date', 'label' => 'Date of observation', 'required' => true, 'not_future' => true],
    'hour_band'      => ['type' => 'int', 'label' => 'Time band', 'required' => true, 'min' => 0, 'max' => 5],
    'day_type'       => ['type' => 'enum', 'label' => 'Day type', 'required' => true,
                          'options' => ['weekday', 'saturday', 'sunday', 'holiday']],
    'people_in'      => ['type' => 'int', 'label' => 'People entering', 'min' => 0, 'max' => 200000],
    'people_out'     => ['type' => 'int', 'label' => 'People leaving', 'min' => 0, 'max' => 200000],
    'traders_present'=> ['type' => 'int', 'label' => 'Traders present', 'min' => 0, 'max' => 65535],
    'source'         => ['type' => 'enum', 'label' => 'How counted', 'required' => true,
                          'options' => ['manual-count', 'sensor', 'estimate', 'trader-log']],
    'observer'       => ['type' => 'text', 'label' => 'Observer', 'max' => 140],
    'weather'        => ['type' => 'enum', 'label' => 'Weather', 'options' => ['clear', 'rain', 'overcast']],
    'event_note'     => ['type' => 'text', 'label' => 'Event note', 'max' => 160],
];

switch ($action) {

    case 'record': {
        requirePerm('report');

        $v = new Validator($_POST, $rules);
        $v->checkExists('market_id', 'markets', $_POST['market_id'] ?? null);

        /* A band with nobody counted at all is a gap, not an observation. */
        if (($v->value('people_in') === 0) && ($v->value('people_out') === 0)) {
            jsonOut([
                'ok'     => false,
                'error'  => 'Enter how many people came in and went out. A band with no count is a gap, not an observation.',
                'errors' => ['people_in' => 'A count of zero in and zero out is not an observation.'],
            ], 422);
        }

        if ($v->fails()) {
            jsonOut([
                'ok'     => false,
                'error'  => 'The count could not be saved. Correct the highlighted fields.',
                'errors' => $v->errors(),
                'values' => $_POST,
            ], 422);
        }

        $data = $v->validated();
        $data['created_by'] = Auth::id() ?: null;

        $id = Database::insert('footfall_observations', $data);

        /* Report the new profile so the operator sees the effect of the count
           rather than having to go and look. */
        $market = Database::one('SELECT * FROM markets WHERE id = ?', [(int) $data['market_id']]);
        $profile = ReachModel::footfall((int) $data['market_id'], $market);

        Auth::log('record-footfall', 'footfall_observations', $id,
            sprintf('%s on %s: %d in, %d out',
                $market['name'] ?? 'market', $data['observed_on'],
                (int) $data['people_in'], (int) $data['people_out']));

        jsonOut([
            'ok'      => true,
            'message' => sprintf(
                'Count recorded. %s now reads about %s people a day, at %d%% confidence.',
                $market['name'] ?? 'The market', num($profile['daily']),
                (int) round($profile['confidence'] * 100)),
            'id'      => $id,
            'profile' => $profile,
        ]);
    }

    case 'delete': {
        requirePerm('manage');
        $id = (int) ($_POST['observation_id'] ?? 0);
        $row = Database::one('SELECT * FROM footfall_observations WHERE id = ?', [$id]);
        if (!$row) {
            jsonOut(['ok' => false, 'error' => 'That count does not exist.'], 404);
        }
        Database::run('DELETE FROM footfall_observations WHERE id = ?', [$id]);
        Auth::log('delete-footfall', 'footfall_observations', $id, 'Observation removed');
        jsonOut(['ok' => true, 'message' => 'The count has been removed.']);
    }

    default:
        jsonOut(['error' => 'Unknown action'], 400);
}
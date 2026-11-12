<?php
// Общие заметки (не привязанные к задаче). Диапазон дат необязателен:
// календарь передаёт start/end, страница «Заметки» — нет.
require __DIR__ . '/db.php';
require_method('GET');

$userId = require_int($_GET['user_id'] ?? null, 'user_id');
$start  = parse_datetime($_GET['start'] ?? null, 'start');
$end    = parse_datetime($_GET['end'] ?? null, 'end');

$sql    = 'SELECT note_id, content, create_date FROM note WHERE user_id = ? AND task_id IS NULL';
$params = [$userId];
if ($start !== null && $end !== null) {
    $sql     .= ' AND create_date >= ? AND create_date < ?';
    $params[] = $start;
    $params[] = $end;
}
$sql .= ' ORDER BY create_date DESC';

$notes = array_map(fn($r) => [
    'id'            => 'note-' . $r['note_id'],
    'title'         => mb_strimwidth($r['content'], 0, 40, '…'),
    'start'         => iso($r['create_date']),
    'extendedProps' => [
        'kind'        => 'note',
        'noteId'      => (int)$r['note_id'],
        'noteContent' => $r['content'],
    ],
], query($sql, $params)->fetchAll());

json_response($notes);

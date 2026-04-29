<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/db.php';

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function isValidDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function migrateLegacyNotesTable(PDO $pdo): void
{
    $chk = $pdo->query(
        "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notes' AND COLUMN_NAME = 'id'"
    );
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if ($row && (int) $row['c'] > 0) {
        return;
    }

    // Tabla antigua (note_date PRIMARY sin id): migrar sin perder filas existentes.
    try {
        $pdo->exec('DROP TABLE IF EXISTS notes_new');
        $pdo->exec('CREATE TABLE notes_new (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            note_date DATE NOT NULL,
            note_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_note_date (note_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        $pdo->exec('INSERT INTO notes_new (note_date, note_text) SELECT note_date, note_text FROM notes');
        $pdo->exec('DROP TABLE notes');
        $pdo->exec('RENAME TABLE notes_new TO notes');
    } catch (Throwable $e) {
        respond(500, ['ok' => false, 'error' => 'No se pudo migrar notes a varias por día: ' . $e->getMessage()]);
    }
}

function ensureTable(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS notes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        note_date DATE NOT NULL,
        note_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_note_date (note_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    migrateLegacyNotesTable($pdo);
}

try {
    ensureTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $month = $_GET['month'] ?? null;
        $date = $_GET['date'] ?? null;

        if ($month !== null) {
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                respond(400, ['ok' => false, 'error' => 'Mes inválido. Formato esperado: YYYY-MM']);
            }

            $stmt = $pdo->prepare(
                "SELECT id, note_date, note_text FROM notes
                 WHERE DATE_FORMAT(note_date, '%Y-%m') = :month
                 ORDER BY note_date ASC, id ASC"
            );
            $stmt->execute(['month' => $month]);
            $rows = $stmt->fetchAll();

            $notes = [];
            foreach ($rows as $row) {
                $d = $row['note_date'];
                if (!isset($notes[$d])) {
                    $notes[$d] = [];
                }
                $notes[$d][] = [
                    'id' => (int) $row['id'],
                    'note_text' => $row['note_text'],
                ];
            }

            respond(200, ['ok' => true, 'notes' => $notes]);
        }

        if ($date === null || !isValidDate($date)) {
            respond(400, ['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD']);
        }

        $stmt = $pdo->prepare(
            "SELECT id, note_text FROM notes WHERE note_date = :date ORDER BY id ASC"
        );
        $stmt->execute(['date' => $date]);
        $list = [];
        foreach ($stmt->fetchAll() as $row) {
            $list[] = [
                'id' => (int) $row['id'],
                'note_text' => $row['note_text'],
            ];
        }

        respond(200, ['ok' => true, 'date' => $date, 'items' => $list]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput ?? '', true) ?: [];

        $action = $json['action'] ?? $_POST['action'] ?? 'add';

        if ($action === 'delete') {
            $id = isset($json['id']) ? (int) $json['id'] : (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                respond(400, ['ok' => false, 'error' => 'ID de nota inválido']);
            }
            $stmt = $pdo->prepare('DELETE FROM notes WHERE id = :id');
            $stmt->execute(['id' => $id]);
            respond(200, ['ok' => true, 'message' => 'Nota eliminada', 'id' => $id]);
        }

        $date = trim((string) ($json['date'] ?? $_POST['date'] ?? ''));
        $note = trim((string) ($json['note'] ?? $_POST['note'] ?? ''));

        if (!isValidDate($date)) {
            respond(400, ['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD']);
        }

        if ($note === '') {
            respond(400, ['ok' => false, 'error' => 'La nota no puede estar vacía']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO notes (note_date, note_text) VALUES (:date, :note)'
        );
        $stmt->execute([
            'date' => $date,
            'note' => $note,
        ]);
        $newId = (int) $pdo->lastInsertId();

        respond(200, [
            'ok' => true,
            'message' => 'Nota guardada',
            'date' => $date,
            'id' => $newId,
            'note' => $note,
        ]);
    }

    respond(405, ['ok' => false, 'error' => 'Método no permitido']);
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

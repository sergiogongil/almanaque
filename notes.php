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

function ensureTable(PDO $pdo): void
{
    $sql = "CREATE TABLE IF NOT EXISTS notes (
        note_date DATE PRIMARY KEY,
        note_text TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $pdo->exec($sql);
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
                "SELECT note_date, note_text FROM notes
                 WHERE DATE_FORMAT(note_date, '%Y-%m') = :month"
            );
            $stmt->execute(['month' => $month]);
            $rows = $stmt->fetchAll();

            $notes = [];
            foreach ($rows as $row) {
                $notes[$row['note_date']] = $row['note_text'];
            }

            respond(200, ['ok' => true, 'notes' => $notes]);
        }

        if ($date === null || !isValidDate($date)) {
            respond(400, ['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD']);
        }

        $stmt = $pdo->prepare("SELECT note_text FROM notes WHERE note_date = :date LIMIT 1");
        $stmt->execute(['date' => $date]);
        $row = $stmt->fetch();

        respond(200, ['ok' => true, 'date' => $date, 'note' => $row['note_text'] ?? '']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawInput = file_get_contents('php://input');
        $json = json_decode($rawInput ?? '', true);

        $date = trim((string)($json['date'] ?? $_POST['date'] ?? ''));
        $note = trim((string)($json['note'] ?? $_POST['note'] ?? ''));

        if (!isValidDate($date)) {
            respond(400, ['ok' => false, 'error' => 'Fecha inválida. Formato esperado: YYYY-MM-DD']);
        }

        if ($note === '') {
            $stmt = $pdo->prepare("DELETE FROM notes WHERE note_date = :date");
            $stmt->execute(['date' => $date]);
            respond(200, ['ok' => true, 'message' => 'Nota eliminada', 'date' => $date, 'note' => '']);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO notes (note_date, note_text)
             VALUES (:date, :note)
             ON DUPLICATE KEY UPDATE note_text = VALUES(note_text)"
        );
        $stmt->execute([
            'date' => $date,
            'note' => $note
        ]);

        respond(200, ['ok' => true, 'message' => 'Nota guardada', 'date' => $date, 'note' => $note]);
    }

    respond(405, ['ok' => false, 'error' => 'Método no permitido']);
} catch (Throwable $e) {
    respond(500, ['ok' => false, 'error' => 'Error interno: ' . $e->getMessage()]);
}

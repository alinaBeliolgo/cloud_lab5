<?php
$master_host = "";
$replica_host = "";
$db_user = "admin";
$db_pass = "";
$db_name = "project_db";
$db_port = 3306;

// Подключение к master
$master = new PDO("mysql:host=$master_host;port=$db_port;dbname=$db_name;charset=utf8", $db_user, $db_pass);
$master->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Подключение к replica
$replica = new PDO("mysql:host=$replica_host;port=$db_port;dbname=$db_name;charset=utf8", $db_user, $db_pass);
$replica->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// --- Добавление новой задачи ---
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $category_id = $_POST['category_id'];
    $status = $_POST['status'];
    $stmt = $master->prepare("INSERT INTO todos (title, category_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$title, $category_id, $status]);
    header("Location: index.php");
    exit;
} elseif ($action === 'add') {
    // Форма добавления
    echo '<h2>Добавить задачу</h2>
          <form method="POST">
            Title: <input type="text" name="title"><br>
            Category ID: <input type="number" name="category_id"><br>
            Status: <input type="text" name="status"><br>
            <input type="submit" value="Добавить">
          </form>';
    exit;
}

// --- Обновление задачи ---
if ($action === 'update' && $id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = $_POST['title'];
        $category_id = $_POST['category_id'];
        $status = $_POST['status'];
        $stmt = $master->prepare("UPDATE todos SET title=?, category_id=?, status=? WHERE id=?");
        $stmt->execute([$title, $category_id, $status, $id]);
        header("Location: index.php");
        exit;
    }
    // Форма редактирования
    $stmt = $replica->prepare("SELECT * FROM todos WHERE id=?");
    $stmt->execute([$id]);
    $todo = $stmt->fetch(PDO::FETCH_ASSOC);
    echo '<h2>Редактировать задачу</h2>
          <form method="POST">
            Title: <input type="text" name="title" value="'.$todo['title'].'"><br>
            Category ID: <input type="number" name="category_id" value="'.$todo['category_id'].'"><br>
            Status: <input type="text" name="status" value="'.$todo['status'].'"><br>
            <input type="submit" value="Сохранить">
          </form>';
    exit;
}

// --- Удаление задачи ---
if ($action === 'delete' && $id) {
    $stmt = $master->prepare("DELETE FROM todos WHERE id=?");
    $stmt->execute([$id]);
    header("Location: index.php");
    exit;
}

// --- Чтение всех задач (replica) ---
$stmt = $replica->query("SELECT todos.id, todos.title, todos.status, categories.name AS category_name
                         FROM todos
                         JOIN categories ON todos.category_id = categories.id");
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Список задач (через read replica)</h2>";
foreach ($todos as $todo) {
    echo "{$todo['id']}. {$todo['title']} [{$todo['status']}] - Категория: {$todo['category_name']} ";
    echo "<a href='?action=update&id={$todo['id']}'>Редактировать</a> | ";
    echo "<a href='?action=delete&id={$todo['id']}'>Удалить</a><br>";
}

echo "<br><a href='?action=add'>Добавить новую задачу</a>";
?>

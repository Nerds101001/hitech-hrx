<?php
require_once __DIR__ . '/config/database.php';

try {
    global $pdo;
    
    // Data from the screenshot
    $title = 'Morning Dynamic Flow'; // Guessed title as it was hidden behind the alert
    $description = 'A dynamic and balanced class designed to challenge both your muscle power and your range of motion. We will hold key poses to build foundational strength while incorporating deep, targeted stretches to unlock flexibility and improve overall posture.';
    $day_of_week = 'Tuesday';
    $class_date = '0000-00-00 00:00:00'; // Recurring
    $start_time = '06:00:00';
    $end_time = '06:45:00';
    $live_link = 'https://meet.google.com/gih-zyik-zgo';
    $max_participants = 50;
    $class_type = 'Beginner';

    $stmt = $pdo->prepare('INSERT INTO classes (title, description, day_of_week, class_date, start_time, end_time, live_link, max_participants, class_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$title, $description, $day_of_week, $class_date, $start_time, $end_time, $live_link, $max_participants, $class_type]);
    
    echo "Class successfully published!";
} catch (Exception $e) {
    echo "Error inserting class: " . $e->getMessage();
}

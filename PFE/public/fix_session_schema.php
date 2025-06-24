<?php
require __DIR__ . '/../config/config.php';

echo "<h1>Database Schema Fix for Sessions</h1>";

try {
    // Check if descriptionSession column exists
    echo "<h2>Checking Session table schema...</h2>";
    
    $stmt = $pdo->query("DESCRIBE Session");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_description = false;
    echo "Current Session table columns:<br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        if ($column['Field'] === 'descriptionSession') {
            $has_description = true;
        }
    }
    
    if ($has_description) {
        echo "<p style='color: green;'>✅ descriptionSession column already exists!</p>";
    } else {
        echo "<p style='color: red;'>❌ descriptionSession column missing. Adding it...</p>";
        
        // Add the missing column
        $pdo->exec("ALTER TABLE Session ADD COLUMN descriptionSession TEXT AFTER titreSession");
        
        echo "<p style='color: green;'>✅ descriptionSession column added successfully!</p>";
        
        // Verify it was added
        $stmt = $pdo->query("DESCRIBE Session");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Updated Session table columns:<br>";
        foreach ($columns as $column) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        }
    }
    
    // Check existing sessions
    echo "<h2>Checking existing sessions...</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM Session");
    $result = $stmt->fetch();
    echo "Total sessions in database: " . $result['total'] . "<br>";
    
    // Show recent sessions
    $stmt = $pdo->query("
        SELECT s.idSession, s.titreSession, s.descriptionSession, s.dateSession, s.heureSession, s.statutSession,
               u.prenomUtilisateur, u.nomUtilisateur
        FROM Session s
        LEFT JOIN Mentor m ON s.idMentorAnimateur = m.idMentor
        LEFT JOIN Utilisateur u ON m.idUtilisateur = u.idUtilisateur
        ORDER BY s.idSession DESC
        LIMIT 5
    ");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "No sessions found in database.<br>";
    } else {
        echo "Recent sessions:<br>";
        foreach ($sessions as $session) {
            echo "- ID: {$session['idSession']}, Title: " . htmlspecialchars($session['titreSession']) . 
                 ", Mentor: " . htmlspecialchars($session['prenomUtilisateur'] . ' ' . $session['nomUtilisateur']) .
                 ", Date: {$session['dateSession']} {$session['heureSession']}, Status: {$session['statutSession']}<br>";
        }
    }
    
    echo "<h2>Schema fix complete!</h2>";
    echo "<p>The database schema has been updated. You can now try publishing sessions again.</p>";
    echo "<p><a href='mentor_dashboard.php'>← Back to Mentor Dashboard</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}
?>

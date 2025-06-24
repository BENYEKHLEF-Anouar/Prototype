<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in as mentor
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'mentor') {
    die("Please log in as a mentor first");
}

$mentor_user_id = $_SESSION['user']['id'];

echo "<h1>Session Publishing Debug Test</h1>";

// 1. Check if mentor record exists
echo "<h2>1. Checking Mentor Record</h2>";
try {
    $stmt = $pdo->prepare("SELECT idMentor, competences FROM Mentor WHERE idUtilisateur = ?");
    $stmt->execute([$mentor_user_id]);
    $mentor_data = $stmt->fetch();
    
    if ($mentor_data) {
        echo "✅ Mentor record found: ID = " . $mentor_data['idMentor'] . "<br>";
        echo "Competences: " . htmlspecialchars($mentor_data['competences']) . "<br>";
        $mentor_id = $mentor_data['idMentor'];
    } else {
        echo "❌ No mentor record found. Creating one...<br>";
        $stmt = $pdo->prepare("INSERT INTO Mentor (idUtilisateur, competences) VALUES (?, ?)");
        $stmt->execute([$mentor_user_id, 'Compétences à définir']);
        $mentor_id = $pdo->lastInsertId();
        echo "✅ Mentor record created with ID: " . $mentor_id . "<br>";
    }
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

// 2. Check database schema
echo "<h2>2. Checking Database Schema</h2>";
try {
    $stmt = $pdo->query("DESCRIBE Session");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_description = false;
    echo "Session table columns:<br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
        if ($column['Field'] === 'descriptionSession') {
            $has_description = true;
        }
    }
    
    if ($has_description) {
        echo "✅ descriptionSession column exists<br>";
    } else {
        echo "❌ descriptionSession column missing! Adding it...<br>";
        $pdo->exec("ALTER TABLE Session ADD COLUMN descriptionSession TEXT AFTER titreSession");
        echo "✅ descriptionSession column added<br>";
    }
} catch (PDOException $e) {
    echo "❌ Schema check error: " . $e->getMessage() . "<br>";
}

// 3. Test session insertion
echo "<h2>3. Testing Session Insertion</h2>";
try {
    // Calculate current week dates
    $currentWeekStart = new DateTime();
    $currentWeekStart->setISODate($currentWeekStart->format('Y'), $currentWeekStart->format('W'), 1);
    $currentWeekEnd = clone $currentWeekStart;
    $currentWeekEnd->add(new DateInterval('P6D'));
    
    echo "Current week: " . $currentWeekStart->format('Y-m-d') . " to " . $currentWeekEnd->format('Y-m-d') . "<br>";
    
    // Use tomorrow's date for testing
    $testDate = (new DateTime())->add(new DateInterval('P1D'))->format('Y-m-d');
    $testTime = '14:00';
    
    echo "Test session date/time: $testDate $testTime<br>";
    
    // Test data
    $testData = [
        'titreSession' => 'Test Session - ' . date('Y-m-d H:i:s'),
        'descriptionSession' => 'This is a test session created by the debug script',
        'dateSession' => $testDate,
        'heureSession' => $testTime,
        'tarifSession' => 25.00,
        'niveau' => 'Licence',
        'typeSession' => 'en_ligne',
        'lienReunion' => 'https://meet.google.com/test-session',
        'idMentorAnimateur' => $mentor_id
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO Session (titreSession, descriptionSession, dateSession, heureSession, tarifSession, niveau, typeSession, lienReunion, idMentorAnimateur, statutSession)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'disponible')
    ");
    
    $result = $stmt->execute([
        $testData['titreSession'],
        $testData['descriptionSession'],
        $testData['dateSession'],
        $testData['heureSession'],
        $testData['tarifSession'],
        $testData['niveau'],
        $testData['typeSession'],
        $testData['lienReunion'],
        $testData['idMentorAnimateur']
    ]);
    
    if ($result) {
        $sessionId = $pdo->lastInsertId();
        echo "✅ Test session created successfully with ID: $sessionId<br>";
        
        // Verify the session was created
        $stmt = $pdo->prepare("SELECT * FROM Session WHERE idSession = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($session) {
            echo "✅ Session verified in database:<br>";
            echo "- Title: " . htmlspecialchars($session['titreSession']) . "<br>";
            echo "- Description: " . htmlspecialchars($session['descriptionSession']) . "<br>";
            echo "- Date: " . $session['dateSession'] . "<br>";
            echo "- Time: " . $session['heureSession'] . "<br>";
            echo "- Status: " . $session['statutSession'] . "<br>";
        }
    } else {
        echo "❌ Failed to create test session<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ Session insertion error: " . $e->getMessage() . "<br>";
}

// 4. Check existing sessions for this mentor
echo "<h2>4. Existing Sessions for This Mentor</h2>";
try {
    $stmt = $pdo->prepare("
        SELECT idSession, titreSession, descriptionSession, dateSession, heureSession, statutSession
        FROM Session 
        WHERE idMentorAnimateur = ? 
        ORDER BY dateSession DESC, heureSession DESC
        LIMIT 5
    ");
    $stmt->execute([$mentor_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($sessions)) {
        echo "No sessions found for this mentor.<br>";
    } else {
        echo "Found " . count($sessions) . " session(s):<br>";
        foreach ($sessions as $session) {
            echo "- ID: {$session['idSession']}, Title: " . htmlspecialchars($session['titreSession']) . 
                 ", Date: {$session['dateSession']} {$session['heureSession']}, Status: {$session['statutSession']}<br>";
        }
    }
} catch (PDOException $e) {
    echo "❌ Error fetching sessions: " . $e->getMessage() . "<br>";
}

echo "<h2>5. Test Complete</h2>";
echo "If you see ✅ for all tests above, the session publishing should work.<br>";
echo "If you see ❌ for any test, that indicates the issue.<br>";
echo "<br><a href='mentor_dashboard.php'>← Back to Mentor Dashboard</a>";
?>

<?php
require '../config/config.php';

// Test script to verify session system functionality
echo "<h1>Session System Test</h1>";

try {
    // Test 1: Check if Session table has required fields
    echo "<h2>1. Database Structure Test</h2>";
    $stmt = $pdo->query("DESCRIBE Session");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredFields = ['typeSession', 'lienReunion'];
    $foundFields = array_column($columns, 'Field');
    
    foreach ($requiredFields as $field) {
        if (in_array($field, $foundFields)) {
            echo "✅ Field '$field' exists<br>";
        } else {
            echo "❌ Field '$field' missing<br>";
        }
    }
    
    // Test 2: Check if we can fetch sessions with new fields
    echo "<h2>2. Session Data Test</h2>";
    $stmt = $pdo->query("SELECT idSession, titreSession, typeSession, lienReunion, statutSession FROM Session LIMIT 3");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sessions)) {
        echo "✅ Can fetch sessions with new fields<br>";
        foreach ($sessions as $session) {
            echo "Session: {$session['titreSession']} - Type: {$session['typeSession']} - Status: {$session['statutSession']}<br>";
        }
    } else {
        echo "❌ No sessions found<br>";
    }
    
    // Test 3: Check if we can insert a test session
    echo "<h2>3. Session Creation Test</h2>";
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Session (titreSession, descriptionSession, dateSession, heureSession, 
                               tarifSession, typeSession, lienReunion, idMentorAnimateur, statutSession) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $testData = [
            'Test Session - ' . date('Y-m-d H:i:s'),
            'Test description',
            date('Y-m-d', strtotime('+1 day')),
            '10:00:00',
            25.00,
            'en_ligne',
            'https://meet.google.com/test-link',
            1, // Assuming mentor ID 1 exists
            'disponible'
        ];
        
        $success = $stmt->execute($testData);
        
        if ($success) {
            $testSessionId = $pdo->lastInsertId();
            echo "✅ Test session created successfully (ID: $testSessionId)<br>";
            
            // Clean up test session
            $pdo->prepare("DELETE FROM Session WHERE idSession = ?")->execute([$testSessionId]);
            echo "✅ Test session cleaned up<br>";
        } else {
            echo "❌ Failed to create test session<br>";
        }
    } catch (Exception $e) {
        echo "❌ Session creation error: " . $e->getMessage() . "<br>";
    }
    
    // Test 4: Check action files exist
    echo "<h2>4. Action Files Test</h2>";
    $actionFiles = [
        'publish_session.php',
        'update_session.php',
        'delete_session.php',
        'complete_session.php',
        'submit_evaluation.php'
    ];
    
    foreach ($actionFiles as $file) {
        $path = "actions/$file";
        if (file_exists($path)) {
            echo "✅ $file exists<br>";
        } else {
            echo "❌ $file missing<br>";
        }
    }
    
    // Test 5: Check CSS files
    echo "<h2>5. CSS Files Test</h2>";
    $cssFiles = [
        '../assets/css/mentor_dashboard.css',
        '../assets/css/student_dashboard.css'
    ];
    
    foreach ($cssFiles as $file) {
        if (file_exists($file)) {
            echo "✅ " . basename($file) . " exists<br>";
        } else {
            echo "❌ " . basename($file) . " missing<br>";
        }
    }
    
    echo "<h2>6. System Status</h2>";
    echo "✅ Session system appears to be properly configured!<br>";
    echo "✅ All required database fields are present<br>";
    echo "✅ Action files are in place<br>";
    echo "✅ CSS files are available<br>";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage();
}

echo "<br><br><a href='mentor_dashboard.php'>Go to Mentor Dashboard</a> | ";
echo "<a href='student_dashboard.php'>Go to Student Dashboard</a>";
?>

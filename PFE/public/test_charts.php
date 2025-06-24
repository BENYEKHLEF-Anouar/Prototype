<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Charts - Mentora</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f8fafc;
        }
        .chart-container {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            max-width: 800px;
        }
        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }
        h1, h2 {
            color: #1e293b;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <h1>Chart.js Test Page</h1>
    
    <div id="status" class="status">Testing Chart.js availability...</div>
    
    <div class="chart-container">
        <h2>Mentor Dashboard Style Chart (Bar)</h2>
        <div class="chart-wrapper">
            <canvas id="mentorChart"></canvas>
        </div>
    </div>
    
    <div class="chart-container">
        <h2>Student Dashboard Style Chart (Line)</h2>
        <div class="chart-wrapper">
            <canvas id="studentChart"></canvas>
        </div>
    </div>

    <script>
        const statusDiv = document.getElementById('status');
        
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            statusDiv.textContent = 'ERROR: Chart.js is not loaded!';
            statusDiv.className = 'status error';
        } else {
            statusDiv.textContent = 'SUCCESS: Chart.js is loaded and available!';
            statusDiv.className = 'status success';
            
            // Test data
            const labels = ['Janv', 'Févr', 'Mars', 'Avr', 'Mai', 'Juin'];
            const mentorData = [2, 5, 3, 8, 6, 4];
            const studentData = [1, 3, 2, 4, 3, 2];
            
            // Create mentor chart (bar)
            try {
                const mentorChart = new Chart(document.getElementById('mentorChart'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sessions terminées',
                            data: mentorData,
                            backgroundColor: 'rgba(37, 99, 235, 0.8)',
                            borderColor: 'rgba(37, 99, 235, 1)',
                            borderWidth: 1,
                            borderRadius: 6,
                            borderSkipped: false,
                            barPercentage: 0.6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
                console.log('Mentor chart created successfully!');
            } catch (error) {
                console.error('Error creating mentor chart:', error);
            }
            
            // Create student chart (line)
            try {
                const studentChart = new Chart(document.getElementById('studentChart'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sessions terminées',
                            data: studentData,
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            borderColor: 'rgba(37, 99, 235, 1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgba(37, 99, 235, 1)',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { stepSize: 1 }
                            }
                        }
                    }
                });
                console.log('Student chart created successfully!');
            } catch (error) {
                console.error('Error creating student chart:', error);
            }
        }
    </script>
</body>
</html>

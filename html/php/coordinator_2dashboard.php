<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
        }
        .dashboard-container {
            width: 90%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: auto auto;
            grid-gap: 15px;
            padding: 20px;
        }
        .tally-box {
            display: flex;
            align-items: center;
            justify-content: space-evenly;
            background: white;
            padding: 30px;
            border-radius: 15px;
            border: 3px solid #003b1f;
        }
        .tally-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .tally-item img {
            width: 80px;
            height: 80px;
        }
        .tally-count {
            background: #003b1f;
            color: white;
            padding: 15px 30px;
            border-radius: 15px;
            text-align: center;
            font-size: 1.2em;
        }
        .tally-count span {
            display: block;
            font-weight: bold;
        }
        .trainee-chart, .reminders, .worklog {
            background: white;
            padding: 20px;
            border-radius: 15px;
            border: 3px solid #003b1f;
        }
        .trainee-chart {
            grid-column: span 2;
        }
        .header {
            width: 100%;
            padding: 15px;
            background: #003b1f;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: red;
            border-radius: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>Welcome, <?php echo $_SESSION['username']; ?>!</h2>
        <a href="logout.php">Logout</a>
    </div>

    <div class="dashboard-container">
        <div class="tally-box">
            <div class="tally-item">
                <img src="studentTally.png" alt="Trainees">
                <div class="tally-count">
                    <span>TRAINEES</span>
                    <span>60</span>
                </div>
            </div>
            <div class="tally-item">
                <img src="companyTally.png" alt="Company">
                <div class="tally-count">
                    <span>COMPANY</span>
                    <span>14</span>
                </div>
            </div>
        </div>
        <div class="trainee-chart">
            <h3>NUMBER OF TRAINEES PER SECTION</h3>
            <img src="chart.png" alt="Trainee Chart" width="100%">
        </div>
        <div class="reminders">
            <h3>REMINDERS</h3>
        </div>
        <div class="worklog">
            <h3>WORKLOG</h3>
        </div>
    </div>

</body>
</html>

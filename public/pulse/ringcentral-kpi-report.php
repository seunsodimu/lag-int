<?php
/**
 * RingCentral KPI Report Display
 * 
 * Displays KPI metrics and user performance data from the RingCentral KPI Excel sheet
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Laguna\Integration\Middleware\AuthMiddleware;
use Laguna\Integration\Utils\UrlHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;

date_default_timezone_set('America/New_York');

$config = require __DIR__ . '/../../config/config.php';


$kpiData = [];
$userData = [];
$error = null;
$fileModifiedTime = null;

$excelFile = __DIR__ . '/../../uploads/ringcentral_kpi/RingCentral_Customer_Service_KPI.xlsx';

if (!file_exists($excelFile)) {
    $error = 'Excel file not found: ' . $excelFile;
} else {
    $fileModifiedTime = filemtime($excelFile);
    try {
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($excelFile);
        
        $kpiSheet = $spreadsheet->getSheetByName('KPIs');
        if ($kpiSheet) {
            $kpiData = [
                'total_calls' => $kpiSheet->getCell('A2')->getValue(),
                'avg_calls_day' => $kpiSheet->getCell('B2')->getValue(),
                'inbound' => $kpiSheet->getCell('C2')->getValue(),
                'outbound' => $kpiSheet->getCell('D2')->getValue(),
                'missed' => $kpiSheet->getCell('E2')->getValue(),
                'avg_handle_time' => $kpiSheet->getCell('F2')->getValue(),
            ];
        }
        
        $usersSheet = $spreadsheet->getSheetByName('Users');
        if ($usersSheet) {
            $rows = $usersSheet->toArray();
            $headers = array_shift($rows);
            
            foreach ($rows as $row) {
                if (!empty(array_filter($row))) {
                    $userData[] = [
                        'name' => $row[0] ?? '',
                        'status' => $row[1] ?? '',
                        'ext' => $row[2] ?? '',
                        'total_calls' => $row[3] ?? '',
                        'avg_calls_day' => $row[4] ?? '',
                        'inbound' => $row[5] ?? '',
                        'outbound' => $row[6] ?? '',
                        'missed' => $row[7] ?? '',
                        'avg_handle_time' => $row[8] ?? '',
                    ];
                }
            }
        }
        
        $spreadsheet->disconnectWorksheets();
        
    } catch (Exception $e) {
        $error = 'Error reading Excel file: ' . $e->getMessage();
    }
}

$refreshTime = $_GET['refresh_time'] ?? '08:00';

function formatExcelTime($value) {
    if (is_numeric($value)) {
        $seconds = (int)round($value * 86400);
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    return $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RingCentral KPI Report - <?php echo $config['app']['name']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
         body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden; /* Prevent body scroll, use container scrolls instead */
        }
        .dashboard-wrapper {
            display: flex;
            height: 100vh;
            width: 100vw;
            gap: 0;
        }
        
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-left h1 {
            margin: 0;
            font-size: 2em;
            margin-bottom: 5px;
        }
        
        .header-left p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95em;
        }
        
        .header-right {
            text-align: right;
        }
        
        .header-right a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            opacity: 0.9;
            transition: opacity 0.2s;
        }
        
        .header-right a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        
        .content {
            padding: 40px;
        }
        
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        #kpis {
            width: 300px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 20px;
            overflow-y: auto;
            flex-shrink: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .kpi-cards {
            display: flex;
            flex-direction: column; /* Stacked vertically */
            gap: 10px;
        }
        
        .kpi-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 0px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .kpi-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
            transform: translateY(-2px);
        }
        
        .kpi-card .label {
            font-size: 0.7em;
            color: #666;
            margin-bottom: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .kpi-card .value {
            font-size: 1.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        
        .kpi-card.total-calls {
            border-left: 4px solid #667eea;
        }
        
        .kpi-card.avg-calls {
            border-left: 4px solid #764ba2;
        }
        
        .kpi-card.inbound {
            border-left: 4px solid #28a745;
        }
        
        .kpi-card.outbound {
            border-left: 4px solid #ffc107;
        }
        
        .kpi-card.missed {
            border-left: 4px solid #dc3545;
        }
        
        .kpi-card.handle-time {
            border-left: 4px solid #17a2b8;
        }
        
       #kpis {
            width: 300px;
            background: white;
            border-right: 1px solid #e0e0e0;
            padding: 20px;
            overflow-y: auto;
            flex-shrink: 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        
        .kpi-cards {
            display: flex;
            flex-direction: column; /* Stacked vertically */
            gap: 15px;
        }
        
        .kpi-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
            font-size: 0.95em;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.95em;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .refresh-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        
        .last-updated {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
            font-style: italic;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #666;
            font-size: 0.9em;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
        <div class="dashboard-wrapper">
     <div id="kpis">
        <?php if (!empty($kpiData)): ?>
                    <h2 class="section-title"> Customer Service KPI</h2>
                    <div class="kpi-cards">
                        <div class="kpi-card total-calls">
                            <div class="label">Total Calls</div>
                            <div class="value"><?php echo number_format($kpiData['total_calls']); ?></div>
                        </div>
                        <div class="kpi-card avg-calls">
                            <div class="label">Avg Calls/Day</div>
                            <div class="value"><?php echo number_format($kpiData['avg_calls_day'], 1); ?></div>
                        </div>
                        <div class="kpi-card inbound">
                            <div class="label">Inbound Calls</div>
                            <div class="value"><?php echo number_format($kpiData['inbound']); ?></div>
                        </div>
                        <div class="kpi-card outbound">
                            <div class="label">Outbound Calls</div>
                            <div class="value"><?php echo number_format($kpiData['outbound']); ?></div>
                        </div>
                        <div class="kpi-card missed">
                            <div class="label">Missed w/VM (%)</div>
                            <div class="value"><?php echo number_format($kpiData['missed'], 2); ?>%</div>
                        </div>
                        <div class="kpi-card handle-time">
                            <div class="label">Avg Handle Time</div>
                            <div class="value"><?php echo htmlspecialchars(formatExcelTime($kpiData['avg_handle_time'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
    <div class="container">
        
        <div class="content">
            <?php if ($error): ?>
                <div class="error">
                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php else: ?>
                
                
                
                <?php if (!empty($userData)): ?>
                    <h2 class="section-title"> Team</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Extension</th>
                                    <th>Total Calls</th>
                                    <th>Avg Calls/Day</th>
                                    <th>Inbound</th>
                                    <th>Outbound</th>
                                    <th>Missed w/VM (%)</th>
                                    <th>Avg Handle Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userData as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower($user['status']) === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo htmlspecialchars($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['ext']); ?></td>
                                        <td><?php echo number_format($user['total_calls']); ?></td>
                                        <td><?php echo number_format($user['avg_calls_day'], 1); ?></td>
                                        <td><?php echo number_format($user['inbound']); ?></td>
                                        <td><?php echo number_format($user['outbound']); ?></td>
                                        <td><?php echo number_format($user['missed'], 2); ?>%</td>
                                        <td><?php echo htmlspecialchars(formatExcelTime($user['avg_handle_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="last-updated">
                        Last data refresh: <?php echo date('F j, Y \a\t g:i A', $fileModifiedTime); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        
    </div>
   </div>
    <script>
        function setupDailyRefresh(refreshTime) {
            const [hour, minute] = refreshTime.split(':').map(Number);
            
            function scheduleRefresh() {
                const now = new Date();
                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                tomorrow.setHours(hour, minute, 0, 0);
                
                const timeUntilRefresh = tomorrow.getTime() - now.getTime();
                
                setTimeout(() => {
                    location.reload();
                    scheduleRefresh();
                }, timeUntilRefresh);
            }
            
            scheduleRefresh();
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            setupDailyRefresh('<?php echo htmlspecialchars($refreshTime); ?>');
        });
    </script>
</body>
</html>

<?php
require_once '../includes/functions.php';
require_once '../includes/Database.php';
require_once '../includes/ErrorLogger.php';

// Check if user is authenticated
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../auth/');
    exit;
}

$errorLogger = new ErrorLogger();
$db = new Database();

// Handle AJAX requests for real-time data
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'stats':
            echo json_encode(getSystemStats($db, $errorLogger));
            break;
        case 'recent_logs':
            echo json_encode(getRecentLogs($errorLogger));
            break;
        case 'performance':
            echo json_encode(getPerformanceMetrics($db));
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

function getSystemStats($db, $errorLogger) {
    try {
        $pdo = $db->getConnection();
        
        // Get submission statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total_submissions FROM submissions");
        $totalSubmissions = $stmt->fetch()['total_submissions'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as today_submissions FROM submissions WHERE DATE(created_at) = CURDATE()");
        $todaySubmissions = $stmt->fetch()['today_submissions'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as active_forms FROM forms WHERE status = 'active'");
        $activeForms = $stmt->fetch()['active_forms'];
        
        // Get file statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total_files, SUM(file_size) as total_size FROM files");
        $fileStats = $stmt->fetch();
        
        // Get log statistics
        $logStats = $errorLogger->getLogStats();
        
        // Get disk usage
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        $diskUsed = $diskTotal - $diskFree;
        $diskUsagePercent = ($diskUsed / $diskTotal) * 100;
        
        return [
            'submissions' => [
                'total' => $totalSubmissions,
                'today' => $todaySubmissions,
                'active_forms' => $activeForms
            ],
            'files' => [
                'count' => $fileStats['total_files'] ?? 0,
                'size' => formatBytes($fileStats['total_size'] ?? 0)
            ],
            'logs' => $logStats,
            'disk' => [
                'used_percent' => round($diskUsagePercent, 1),
                'free' => formatBytes($diskFree),
                'total' => formatBytes($diskTotal)
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function getRecentLogs($errorLogger) {
    return [
        'errors' => $errorLogger->getRecentLogs('error.log', 10),
        'security' => $errorLogger->getRecentLogs('security.log', 10),
        'access' => $errorLogger->getRecentLogs('access.log', 20)
    ];
}

function getPerformanceMetrics($db) {
    try {
        $pdo = $db->getConnection();
        
        // Database performance
        $stmt = $pdo->query("SHOW STATUS LIKE 'Queries'");
        $queries = $stmt->fetch()['Value'] ?? 0;
        
        $stmt = $pdo->query("SHOW STATUS LIKE 'Uptime'");
        $uptime = $stmt->fetch()['Value'] ?? 1;
        
        $queriesPerSecond = round($queries / $uptime, 2);
        
        // PHP memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        return [
            'database' => [
                'queries_per_second' => $queriesPerSecond,
                'uptime_hours' => round($uptime / 3600, 1)
            ],
            'php' => [
                'memory_usage' => formatBytes($memoryUsage),
                'memory_peak' => formatBytes($memoryPeak),
                'memory_limit' => $memoryLimit
            ],
            'server' => [
                'load_average' => sys_getloadavg()[0] ?? 'N/A',
                'php_version' => PHP_VERSION
            ]
        ];
        
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Monitoring Dashboard</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .monitoring-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .metric-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .metric-card h3 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 1.1em;
        }
        
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background-color: #007bff;
            transition: width 0.3s ease;
        }
        
        .progress-fill.warning { background-color: #ffc107; }
        .progress-fill.danger { background-color: #dc3545; }
        
        .logs-section {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .log-tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 15px;
        }
        
        .log-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            color: #6c757d;
        }
        
        .log-tab.active {
            color: #007bff;
            border-bottom-color: #007bff;
        }
        
        .log-content {
            display: none;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .log-content.active {
            display: block;
        }
        
        .log-entry {
            padding: 8px 12px;
            border-left: 3px solid #dee2e6;
            margin-bottom: 8px;
            background: #f8f9fa;
            font-family: monospace;
            font-size: 0.85em;
        }
        
        .log-entry.error { border-left-color: #dc3545; }
        .log-entry.warning { border-left-color: #ffc107; }
        .log-entry.info { border-left-color: #17a2b8; }
        
        .refresh-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .auto-refresh {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .refresh-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .refresh-button:hover {
            background: #0056b3;
        }
        
        .last-updated {
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="monitoring-container">
        <h1>System Monitoring Dashboard</h1>
        
        <div class="refresh-controls">
            <div class="auto-refresh">
                <label>
                    <input type="checkbox" id="auto-refresh" checked>
                    Auto-refresh (30s)
                </label>
                <button class="refresh-button" onclick="refreshData()">Refresh Now</button>
            </div>
            <div class="last-updated" id="last-updated">Loading...</div>
        </div>
        
        <div class="dashboard-grid" id="dashboard-grid">
            <!-- Metrics will be populated by JavaScript -->
        </div>
        
        <div class="logs-section">
            <h3>Recent System Logs</h3>
            <div class="log-tabs">
                <div class="log-tab active" onclick="showLogTab('errors')">Errors</div>
                <div class="log-tab" onclick="showLogTab('security')">Security</div>
                <div class="log-tab" onclick="showLogTab('access')">Access</div>
            </div>
            
            <div id="log-errors" class="log-content active">
                <div class="log-entry">Loading error logs...</div>
            </div>
            
            <div id="log-security" class="log-content">
                <div class="log-entry">Loading security logs...</div>
            </div>
            
            <div id="log-access" class="log-content">
                <div class="log-entry">Loading access logs...</div>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval;
        
        function refreshData() {
            Promise.all([
                fetch('?action=stats').then(r => r.json()),
                fetch('?action=recent_logs').then(r => r.json()),
                fetch('?action=performance').then(r => r.json())
            ]).then(([stats, logs, performance]) => {
                updateDashboard(stats, performance);
                updateLogs(logs);
                document.getElementById('last-updated').textContent = `Last updated: ${new Date().toLocaleTimeString()}`;
            }).catch(error => {
                console.error('Error refreshing data:', error);
            });
        }
        
        function updateDashboard(stats, performance) {
            const grid = document.getElementById('dashboard-grid');
            
            grid.innerHTML = `
                <div class="metric-card">
                    <h3>Submissions</h3>
                    <div class="metric-value">${stats.submissions.total}</div>
                    <div class="metric-label">Total Submissions</div>
                    <div style="margin-top: 10px;">
                        <small>Today: ${stats.submissions.today}</small><br>
                        <small>Active Forms: ${stats.submissions.active_forms}</small>
                    </div>
                </div>
                
                <div class="metric-card">
                    <h3>File Storage</h3>
                    <div class="metric-value">${stats.files.count}</div>
                    <div class="metric-label">Files Uploaded</div>
                    <div style="margin-top: 10px;">
                        <small>Total Size: ${stats.files.size}</small>
                    </div>
                </div>
                
                <div class="metric-card">
                    <h3>Disk Usage</h3>
                    <div class="progress-bar">
                        <div class="progress-fill ${stats.disk.used_percent > 90 ? 'danger' : stats.disk.used_percent > 80 ? 'warning' : ''}" 
                             style="width: ${stats.disk.used_percent}%"></div>
                    </div>
                    <div class="metric-label">${stats.disk.used_percent}% used (${stats.disk.free} free)</div>
                </div>
                
                <div class="metric-card">
                    <h3>System Health</h3>
                    <div style="margin-bottom: 10px;">
                        <span class="status-indicator ${stats.logs.recent_errors > 10 ? 'status-error' : stats.logs.recent_errors > 0 ? 'status-warning' : 'status-good'}"></span>
                        Errors: ${stats.logs.recent_errors}
                    </div>
                    <div style="margin-bottom: 10px;">
                        <span class="status-indicator ${stats.logs.recent_security_events > 0 ? 'status-warning' : 'status-good'}"></span>
                        Security Events: ${stats.logs.recent_security_events}
                    </div>
                </div>
                
                <div class="metric-card">
                    <h3>Database Performance</h3>
                    <div class="metric-value">${performance.database.queries_per_second}</div>
                    <div class="metric-label">Queries/Second</div>
                    <div style="margin-top: 10px;">
                        <small>Uptime: ${performance.database.uptime_hours}h</small>
                    </div>
                </div>
                
                <div class="metric-card">
                    <h3>PHP Memory</h3>
                    <div class="metric-value">${performance.php.memory_usage}</div>
                    <div class="metric-label">Current Usage</div>
                    <div style="margin-top: 10px;">
                        <small>Peak: ${performance.php.memory_peak}</small><br>
                        <small>Limit: ${performance.php.memory_limit}</small>
                    </div>
                </div>
            `;
        }
        
        function updateLogs(logs) {
            updateLogSection('errors', logs.errors, 'error');
            updateLogSection('security', logs.security, 'warning');
            updateLogSection('access', logs.access, 'info');
        }
        
        function updateLogSection(type, logEntries, cssClass) {
            const container = document.getElementById(`log-${type}`);
            
            if (!logEntries || logEntries.length === 0) {
                container.innerHTML = '<div class="log-entry">No recent entries</div>';
                return;
            }
            
            container.innerHTML = logEntries.map(entry => `
                <div class="log-entry ${cssClass}">
                    <strong>${entry.timestamp}</strong> - ${entry.message || entry.event || 'Log entry'}
                    ${entry.details ? '<br><small>' + JSON.stringify(entry.details) + '</small>' : ''}
                </div>
            `).join('');
        }
        
        function showLogTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.log-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update content
            document.querySelectorAll('.log-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`log-${tabName}`).classList.add('active');
        }
        
        function setupAutoRefresh() {
            const checkbox = document.getElementById('auto-refresh');
            
            function toggleAutoRefresh() {
                if (checkbox.checked) {
                    refreshInterval = setInterval(refreshData, 30000); // 30 seconds
                } else {
                    clearInterval(refreshInterval);
                }
            }
            
            checkbox.addEventListener('change', toggleAutoRefresh);
            toggleAutoRefresh(); // Initialize
        }
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            refreshData();
            setupAutoRefresh();
        });
    </script>
</body>
</html>
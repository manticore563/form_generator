<?php
require_once '../auth/SessionManager.php';

$sessionManager = new SessionManager();
if (!$sessionManager->isValidSession()) {
    header('Location: /auth/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Management - Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <style>
        .file-manager {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .actions-panel {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .cleanup-results {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
            display: none;
        }
        
        .cleanup-results.error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .file-types-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .file-types-table th,
        .file-types-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .file-types-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="file-manager">
        <h1>File Management</h1>
        
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="stat-value" id="totalFiles">-</div>
                <div class="stat-label">Total Files</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="totalSize">-</div>
                <div class="stat-label">Total Size</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="orphanedFiles">-</div>
                <div class="stat-label">Orphaned Files</div>
            </div>
        </div>
        
        <div class="actions-panel">
            <h3>File Management Actions</h3>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="refreshStats()">
                    <span id="refreshIcon">🔄</span> Refresh Stats
                </button>
                <button class="btn btn-warning" onclick="runCleanup(true)">
                    🔍 Preview Cleanup
                </button>
                <button class="btn btn-danger" onclick="runCleanup(false)">
                    🧹 Run Cleanup
                </button>
            </div>
            
            <div class="cleanup-results" id="cleanupResults"></div>
        </div>
        
        <div class="file-types-section">
            <h3>Files by Type</h3>
            <table class="file-types-table" id="fileTypesTable">
                <thead>
                    <tr>
                        <th>MIME Type</th>
                        <th>Count</th>
                        <th>Total Size</th>
                    </tr>
                </thead>
                <tbody id="fileTypesBody">
                    <tr>
                        <td colspan="3">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Load initial stats
        document.addEventListener('DOMContentLoaded', function() {
            refreshStats();
        });
        
        async function refreshStats() {
            const refreshIcon = document.getElementById('refreshIcon');
            refreshIcon.innerHTML = '<div class="loading"></div>';
            
            try {
                const response = await fetch('/uploads/api.php?action=stats');
                const result = await response.json();
                
                if (result.success) {
                    updateStatsDisplay(result.data);
                } else {
                    console.error('Failed to load stats:', result.error);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            } finally {
                refreshIcon.innerHTML = '🔄';
            }
        }
        
        function updateStatsDisplay(stats) {
            document.getElementById('totalFiles').textContent = formatNumber(stats.total_files || 0);
            document.getElementById('totalSize').textContent = formatBytes(stats.total_size || 0);
            document.getElementById('orphanedFiles').textContent = formatNumber(stats.orphaned_files || 0);
            
            // Update file types table
            const tbody = document.getElementById('fileTypesBody');
            tbody.innerHTML = '';
            
            if (stats.by_type && stats.by_type.length > 0) {
                stats.by_type.forEach(type => {
                    const row = tbody.insertRow();
                    row.insertCell(0).textContent = type.mime_type;
                    row.insertCell(1).textContent = formatNumber(type.count);
                    row.insertCell(2).textContent = formatBytes(type.size);
                });
            } else {
                const row = tbody.insertRow();
                row.insertCell(0).colSpan = 3;
                row.cells[0].textContent = 'No files found';
                row.cells[0].style.textAlign = 'center';
            }
        }
        
        async function runCleanup(dryRun = false) {
            const resultsDiv = document.getElementById('cleanupResults');
            resultsDiv.style.display = 'block';
            resultsDiv.className = 'cleanup-results';
            resultsDiv.innerHTML = '<div class="loading"></div> Running cleanup...';
            
            try {
                const formData = new FormData();
                formData.append('dry_run', dryRun ? 'true' : 'false');
                formData.append('archive_days', '365');
                
                const response = await fetch('/uploads/api.php?action=cleanup', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    let html = '<h4>' + (dryRun ? 'Cleanup Preview' : 'Cleanup Results') + '</h4>';
                    html += '<ul>';
                    html += '<li>Orphaned files: ' + data.orphaned_files + '</li>';
                    html += '<li>Temporary files: ' + data.temp_files + '</li>';
                    html += '<li>Archived files: ' + data.archived_files + '</li>';
                    html += '</ul>';
                    
                    if (data.errors && data.errors.length > 0) {
                        html += '<h5>Errors:</h5><ul>';
                        data.errors.forEach(error => {
                            html += '<li>' + escapeHtml(error) + '</li>';
                        });
                        html += '</ul>';
                        resultsDiv.className = 'cleanup-results error';
                    }
                    
                    resultsDiv.innerHTML = html;
                    
                    // Refresh stats after cleanup
                    if (!dryRun) {
                        setTimeout(refreshStats, 1000);
                    }
                } else {
                    resultsDiv.className = 'cleanup-results error';
                    resultsDiv.innerHTML = '<h4>Error</h4><p>' + escapeHtml(result.error) + '</p>';
                }
            } catch (error) {
                resultsDiv.className = 'cleanup-results error';
                resultsDiv.innerHTML = '<h4>Error</h4><p>' + escapeHtml(error.message) + '</p>';
            }
        }
        
        function formatNumber(num) {
            return new Intl.NumberFormat().format(num);
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
<?php
/**
 * UA 黑名单完整性检查工具
 * 检查 ua_blacklist.txt 中所有条目的格式和可行性
 */

// 分析黑名单文件
function analyzeBlacklistFile($filename = 'ua_blacklist.txt') {
    if (!file_exists($filename)) {
        return [
            'error' => "文件 $filename 不存在",
            'stats' => [],
            'entries' => []
        ];
    }

    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total_lines = count($lines);
    
    $stats = [
        'total_lines' => $total_lines,
        'mixed_format' => 0,  // UA + IP 格式
        'pure_ua' => 0,       // 纯 UA 格式
        'empty_lines' => 0,   // 空行
        'invalid_lines' => 0, // 无效行
        'duplicate_ua' => 0,  // 重复的 UA（仅纯UA格式）
        'duplicate_ip' => 0,  // 重复的 IP
        'extracted_ua' => 0,  // 提取的 UA 数量（去重后）
        'extracted_ip' => 0,  // 提取的 IP 数量
        'ua_with_multiple_ips' => 0  // 同一UA对应多个IP的情况
    ];
    
    $entries = [];
    $ua_seen = [];
    $ip_seen = [];
    $line_number = 0;
    
    foreach ($lines as $line) {
        $line_number++;
        $original_line = $line;
        $line = trim($line);
        
        $entry = [
            'line_number' => $line_number,
            'original' => $original_line,
            'type' => '',
            'ua_part' => '',
            'ip_part' => '',
            'status' => 'valid',
            'issues' => []
        ];
        
        if (empty($line)) {
            $stats['empty_lines']++;
            $entry['type'] = 'empty';
            $entry['status'] = 'warning';
            $entry['issues'][] = '空行';
        } elseif (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
            // 混合格式：UA + IP
            $stats['mixed_format']++;
            $entry['type'] = 'mixed';
            
            $ua_part = trim($matches[1]);
            $ip_part = trim($matches[2]);
            
            $entry['ua_part'] = $ua_part;
            $entry['ip_part'] = $ip_part;
            
            // 验证 UA 部分
            if (empty($ua_part)) {
                $entry['issues'][] = 'UA 部分为空';
                $entry['status'] = 'error';
            } else {
                $ua_lower = strtolower($ua_part);
                if (isset($ua_seen[$ua_lower])) {
                    // 对于混合格式，UA重复是正常的（同一个爬虫可能有多个IP）
                    $stats['ua_with_multiple_ips']++;
                    $entry['issues'][] = "UA 已存在 (首次出现在第 {$ua_seen[$ua_lower]} 行) - 正常情况：同一爬虫的不同IP";
                    // 不标记为警告，因为这是合理的
                } else {
                    $ua_seen[$ua_lower] = $line_number;
                    $stats['extracted_ua']++;
                }
            }
            
            // 验证 IP 部分
            if (empty($ip_part)) {
                $entry['issues'][] = 'IP 部分为空';
                $entry['status'] = 'error';
            } elseif (!filter_var($ip_part, FILTER_VALIDATE_IP)) {
                $entry['issues'][] = 'IP 格式无效';
                $entry['status'] = 'error';
            } else {
                if (isset($ip_seen[$ip_part])) {
                    $stats['duplicate_ip']++;
                    $entry['issues'][] = "重复的 IP (首次出现在第 {$ip_seen[$ip_part]} 行)";
                    $entry['status'] = 'warning';
                } else {
                    $ip_seen[$ip_part] = $line_number;
                    $stats['extracted_ip']++;
                }
                
                // 检查是否为内网IP
                if (filter_var($ip_part, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    $entry['issues'][] = 'IP 为内网地址，可能无效';
                    $entry['status'] = $entry['status'] === 'error' ? 'error' : 'warning';
                }
            }
            
        } else {
            // 纯 UA 格式
            $stats['pure_ua']++;
            $entry['type'] = 'pure_ua';
            $entry['ua_part'] = $line;
            
            if (strlen($line) < 3) {
                $entry['issues'][] = 'UA 过短，可能无效';
                $entry['status'] = 'warning';
            }
            
            $ua_lower = strtolower($line);
            if (isset($ua_seen[$ua_lower])) {
                $stats['duplicate_ua']++;
                $entry['issues'][] = "重复的 UA (首次出现在第 {$ua_seen[$ua_lower]} 行) - 纯UA格式的重复";
                $entry['status'] = 'warning';
            } else {
                $ua_seen[$ua_lower] = $line_number;
                $stats['extracted_ua']++;
            }
        }
        
        if ($entry['status'] === 'error') {
            $stats['invalid_lines']++;
        }
        
        $entries[] = $entry;
    }
    
    return [
        'error' => null,
        'stats' => $stats,
        'entries' => $entries,
        'ua_list' => array_keys($ua_seen),
        'ip_list' => array_keys($ip_seen)
    ];
}

// 执行分析
$analysis = analyzeBlacklistFile();

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>UA 黑名单完整性检查</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { padding: 15px; border-radius: 8px; text-align: center; }
        .stat-card.good { background: #d4edda; border: 1px solid #c3e6cb; }
        .stat-card.warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        .stat-card.error { background: #f8d7da; border: 1px solid #f5c6cb; }
        .stat-number { font-size: 24px; font-weight: bold; }
        .stat-label { font-size: 14px; color: #666; }
        
        .section { margin: 30px 0; }
        .section h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        
        .entry { margin: 10px 0; padding: 10px; border-radius: 5px; border-left: 4px solid; }
        .entry.valid { background: #f8f9fa; border-left-color: #28a745; }
        .entry.warning { background: #fff3cd; border-left-color: #ffc107; }
        .entry.error { background: #f8d7da; border-left-color: #dc3545; }
        
        .entry-header { font-weight: bold; margin-bottom: 5px; }
        .entry-content { font-family: monospace; font-size: 12px; background: rgba(0,0,0,0.05); padding: 5px; border-radius: 3px; margin: 5px 0; }
        .entry-issues { margin-top: 5px; }
        .issue { display: inline-block; background: rgba(220,53,69,0.1); color: #721c24; padding: 2px 6px; border-radius: 3px; margin: 2px; font-size: 11px; }
        
        .filter-buttons { margin: 20px 0; text-align: center; }
        .filter-btn { padding: 8px 16px; margin: 0 5px; border: none; border-radius: 4px; cursor: pointer; }
        .filter-btn.active { background: #007bff; color: white; }
        .filter-btn:not(.active) { background: #e9ecef; color: #333; }
        
        .summary-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .recommendations { background: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8; }
        
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
        
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 UA 黑名单完整性检查报告</h1>
        
        <?php if ($analysis['error']): ?>
            <div class="entry error">
                <div class="entry-header">❌ 错误</div>
                <div><?php echo htmlspecialchars($analysis['error']); ?></div>
            </div>
        <?php else: ?>
            
            <!-- 统计概览 -->
            <div class="stats-grid">
                <div class="stat-card good">
                    <div class="stat-number"><?php echo $analysis['stats']['total_lines']; ?></div>
                    <div class="stat-label">总行数</div>
                </div>
                <div class="stat-card good">
                    <div class="stat-number"><?php echo $analysis['stats']['extracted_ua']; ?></div>
                    <div class="stat-label">有效 UA</div>
                </div>
                <div class="stat-card good">
                    <div class="stat-number"><?php echo $analysis['stats']['extracted_ip']; ?></div>
                    <div class="stat-label">有效 IP</div>
                </div>
                <div class="stat-card <?php echo $analysis['stats']['invalid_lines'] > 0 ? 'error' : 'good'; ?>">
                    <div class="stat-number"><?php echo $analysis['stats']['invalid_lines']; ?></div>
                    <div class="stat-label">无效行</div>
                </div>
                <div class="stat-card <?php echo $analysis['stats']['duplicate_ua'] > 0 ? 'warning' : 'good'; ?>">
                    <div class="stat-number"><?php echo $analysis['stats']['duplicate_ua']; ?></div>
                    <div class="stat-label">重复 UA (纯格式)</div>
                </div>
                <div class="stat-card <?php echo $analysis['stats']['duplicate_ip'] > 0 ? 'warning' : 'good'; ?>">
                    <div class="stat-number"><?php echo $analysis['stats']['duplicate_ip']; ?></div>
                    <div class="stat-label">重复 IP</div>
                </div>
                <div class="stat-card good">
                    <div class="stat-number"><?php echo $analysis['stats']['ua_with_multiple_ips'] ?? 0; ?></div>
                    <div class="stat-label">多IP爬虫</div>
                </div>
            </div>
            
            <!-- 格式分布 -->
            <div class="summary-box">
                <h3>📊 格式分布与分析</h3>
                <p><strong>混合格式 (UA + IP):</strong> <?php echo $analysis['stats']['mixed_format']; ?> 条</p>
                <p><strong>纯 UA 格式:</strong> <?php echo $analysis['stats']['pure_ua']; ?> 条</p>
                <p><strong>空行:</strong> <?php echo $analysis['stats']['empty_lines']; ?> 行</p>
                <p><strong>同一爬虫多IP情况:</strong> <?php echo $analysis['stats']['ua_with_multiple_ips'] ?? 0; ?> 条 <span style="color: #28a745;">✓ 正常</span></p>
                <p><em>注：同一个爬虫（如 GoogleBot、AppleBot）拥有多个IP地址是正常现象</em></p>
            </div>
            
            <!-- 建议 -->
            <?php if ($analysis['stats']['invalid_lines'] > 0 || $analysis['stats']['duplicate_ua'] > 0 || $analysis['stats']['duplicate_ip'] > 0): ?>
            <div class="recommendations">
                <h3>💡 优化建议</h3>
                <ul>
                    <?php if ($analysis['stats']['invalid_lines'] > 0): ?>
                        <li>修复 <?php echo $analysis['stats']['invalid_lines']; ?> 个无效条目</li>
                    <?php endif; ?>
                    <?php if ($analysis['stats']['duplicate_ua'] > 0): ?>
                        <li>删除 <?php echo $analysis['stats']['duplicate_ua']; ?> 个重复的 UA 条目</li>
                    <?php endif; ?>
                    <?php if ($analysis['stats']['duplicate_ip'] > 0): ?>
                        <li>删除 <?php echo $analysis['stats']['duplicate_ip']; ?> 个重复的 IP 条目</li>
                    <?php endif; ?>
                    <?php if ($analysis['stats']['empty_lines'] > 0): ?>
                        <li>清理 <?php echo $analysis['stats']['empty_lines']; ?> 个空行</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- 过滤按钮 -->
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterEntries('all')">全部 (<?php echo count($analysis['entries']); ?>)</button>
                <button class="filter-btn" onclick="filterEntries('error')">错误 (<?php echo array_sum(array_map(function($e) { return $e['status'] === 'error' ? 1 : 0; }, $analysis['entries'])); ?>)</button>
                <button class="filter-btn" onclick="filterEntries('warning')">警告 (<?php echo array_sum(array_map(function($e) { return $e['status'] === 'warning' ? 1 : 0; }, $analysis['entries'])); ?>)</button>
                <button class="filter-btn" onclick="filterEntries('valid')">正常 (<?php echo array_sum(array_map(function($e) { return $e['status'] === 'valid' ? 1 : 0; }, $analysis['entries'])); ?>)</button>
            </div>
            
            <!-- 详细条目列表 -->
            <div class="section">
                <h3>📋 详细条目分析</h3>
                <div id="entries-container">
                    <?php foreach ($analysis['entries'] as $entry): ?>
                        <div class="entry <?php echo $entry['status']; ?>" data-status="<?php echo $entry['status']; ?>">
                            <div class="entry-header">
                                第 <?php echo $entry['line_number']; ?> 行 - 
                                <?php 
                                switch($entry['type']) {
                                    case 'mixed': echo '🔗 混合格式'; break;
                                    case 'pure_ua': echo '🔤 纯 UA'; break;
                                    case 'empty': echo '📝 空行'; break;
                                    default: echo '❓ 未知'; break;
                                }
                                ?>
                                <?php if ($entry['status'] === 'error'): ?>
                                    <span style="color: #dc3545;">❌</span>
                                <?php elseif ($entry['status'] === 'warning'): ?>
                                    <span style="color: #ffc107;">⚠️</span>
                                <?php else: ?>
                                    <span style="color: #28a745;">✅</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="entry-content"><?php echo htmlspecialchars($entry['original']); ?></div>
                            
                            <?php if (!empty($entry['ua_part']) || !empty($entry['ip_part'])): ?>
                                <div style="font-size: 12px; margin-top: 5px;">
                                    <?php if (!empty($entry['ua_part'])): ?>
                                        <strong>UA:</strong> <?php echo htmlspecialchars($entry['ua_part']); ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['ip_part'])): ?>
                                        <strong>IP:</strong> <?php echo htmlspecialchars($entry['ip_part']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($entry['issues'])): ?>
                                <div class="entry-issues">
                                    <?php foreach ($entry['issues'] as $issue): ?>
                                        <span class="issue"><?php echo htmlspecialchars($issue); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 导出功能 -->
            <div class="section">
                <h3>📤 导出和优化</h3>
                <div style="text-align: center; margin: 20px 0;">
                    <button onclick="exportCleanList()" class="filter-btn" style="background: #28a745; color: white;">
                        📋 导出清理后的列表
                    </button>
                    <button onclick="exportProblems()" class="filter-btn" style="background: #dc3545; color: white;">
                        ⚠️ 导出问题条目
                    </button>
                    <button onclick="showStats()" class="filter-btn" style="background: #17a2b8; color: white;">
                        📊 显示详细统计
                    </button>
                </div>

                <div id="export-area" style="display: none;">
                    <h4>导出结果:</h4>
                    <textarea id="export-content" style="width: 100%; height: 300px; font-family: monospace; font-size: 12px;"></textarea>
                    <div style="margin-top: 10px;">
                        <button onclick="copyToClipboard()" class="filter-btn">📋 复制到剪贴板</button>
                        <button onclick="downloadFile()" class="filter-btn">💾 下载文件</button>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

    <!-- 隐藏的数据传递 -->
    <script>
        const analysisData = <?php echo json_encode($analysis, JSON_UNESCAPED_UNICODE); ?>;
    </script>

    <script>
        function filterEntries(status) {
            // 更新按钮状态
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');

            // 过滤条目
            document.querySelectorAll('.entry').forEach(entry => {
                if (status === 'all' || entry.dataset.status === status) {
                    entry.classList.remove('hidden');
                } else {
                    entry.classList.add('hidden');
                }
            });
        }

        function exportCleanList() {
            let cleanList = [];
            let seenUA = new Set();
            let seenIP = new Set();

            analysisData.entries.forEach(entry => {
                if (entry.status === 'valid' || entry.status === 'warning') {
                    if (entry.type === 'mixed') {
                        // 混合格式，检查是否重复
                        const uaKey = entry.ua_part.toLowerCase();
                        const ipKey = entry.ip_part;

                        if (!seenUA.has(uaKey) && !seenIP.has(ipKey)) {
                            cleanList.push(entry.original);
                            seenUA.add(uaKey);
                            seenIP.add(ipKey);
                        }
                    } else if (entry.type === 'pure_ua') {
                        const uaKey = entry.ua_part.toLowerCase();
                        if (!seenUA.has(uaKey)) {
                            cleanList.push(entry.original);
                            seenUA.add(uaKey);
                        }
                    }
                }
            });

            document.getElementById('export-content').value = cleanList.join('\n');
            document.getElementById('export-area').style.display = 'block';
        }

        function exportProblems() {
            let problems = [];

            analysisData.entries.forEach(entry => {
                if (entry.status === 'error' || entry.status === 'warning') {
                    let line = `第${entry.line_number}行: ${entry.original}`;
                    if (entry.issues.length > 0) {
                        line += ` [问题: ${entry.issues.join(', ')}]`;
                    }
                    problems.push(line);
                }
            });

            document.getElementById('export-content').value = problems.join('\n');
            document.getElementById('export-area').style.display = 'block';
        }

        function showStats() {
            let stats = [];
            stats.push('=== UA 黑名单统计报告 ===');
            stats.push(`生成时间: ${new Date().toLocaleString()}`);
            stats.push('');
            stats.push('📊 基本统计:');
            stats.push(`总行数: ${analysisData.stats.total_lines}`);
            stats.push(`有效 UA: ${analysisData.stats.extracted_ua}`);
            stats.push(`有效 IP: ${analysisData.stats.extracted_ip}`);
            stats.push(`无效行: ${analysisData.stats.invalid_lines}`);
            stats.push(`重复 UA: ${analysisData.stats.duplicate_ua}`);
            stats.push(`重复 IP: ${analysisData.stats.duplicate_ip}`);
            stats.push('');
            stats.push('📋 格式分布:');
            stats.push(`混合格式 (UA + IP): ${analysisData.stats.mixed_format}`);
            stats.push(`纯 UA 格式: ${analysisData.stats.pure_ua}`);
            stats.push(`空行: ${analysisData.stats.empty_lines}`);
            stats.push('');

            if (analysisData.ua_list && analysisData.ua_list.length > 0) {
                stats.push('🔤 UA 关键词示例 (前10个):');
                analysisData.ua_list.slice(0, 10).forEach((ua, i) => {
                    stats.push(`${i + 1}. ${ua}`);
                });
                stats.push('');
            }

            if (analysisData.ip_list && analysisData.ip_list.length > 0) {
                stats.push('🌐 IP 地址示例 (前10个):');
                analysisData.ip_list.slice(0, 10).forEach((ip, i) => {
                    stats.push(`${i + 1}. ${ip}`);
                });
            }

            document.getElementById('export-content').value = stats.join('\n');
            document.getElementById('export-area').style.display = 'block';
        }

        function copyToClipboard() {
            const content = document.getElementById('export-content');
            content.select();
            document.execCommand('copy');
            alert('已复制到剪贴板！');
        }

        function downloadFile() {
            const content = document.getElementById('export-content').value;
            const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'blacklist_export_' + new Date().toISOString().slice(0, 10) + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>

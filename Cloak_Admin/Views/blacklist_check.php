<?php
$title = '黑名单检查 - Cloak 管理后台';
$module = 'tools';
?>

<div class="blacklist-check-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #2c3e50;">🔍 黑名单完整性检查</h2>
        <a href="?module=tools" class="btn btn-secondary" style="text-decoration: none;">← 返回工具页面</a>
    </div>

    <!-- 检查摘要 -->
    <div class="summary-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">📊 检查摘要</h3>
        
        <div class="health-score" style="text-align: center; margin-bottom: 25px;">
            <div style="font-size: 48px; font-weight: bold; color: <?php 
                $score = $report['summary']['health_score'];
                echo $score >= 90 ? '#27ae60' : ($score >= 70 ? '#f39c12' : '#e74c3c');
            ?>;">
                <?php echo round($report['summary']['health_score']); ?>%
            </div>
            <div style="font-size: 16px; color: #666; margin-top: 5px;">系统健康分数</div>
        </div>
        
        <div class="summary-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div class="stat-item" style="text-align: center; padding: 20px; background: rgba(231,76,60,0.1); border-radius: 8px;">
                <div style="font-size: 28px; font-weight: bold; color: #e74c3c;"><?php echo $report['summary']['critical_issues']; ?></div>
                <div style="font-size: 14px; color: #666;">严重问题</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 20px; background: rgba(243,156,18,0.1); border-radius: 8px;">
                <div style="font-size: 28px; font-weight: bold; color: #f39c12;"><?php echo $report['summary']['warnings']; ?></div>
                <div style="font-size: 14px; color: #666;">警告</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 20px; background: rgba(52,152,219,0.1); border-radius: 8px;">
                <div style="font-size: 28px; font-weight: bold; color: #3498db;"><?php echo $report['summary']['total_issues']; ?></div>
                <div style="font-size: 14px; color: #666;">总问题数</div>
            </div>
        </div>
    </div>

    <!-- 建议 -->
    <?php if (!empty($report['recommendations'])): ?>
    <div class="recommendations-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">💡 优化建议</h3>
        
        <?php foreach ($report['recommendations'] as $recommendation): ?>
        <div class="recommendation-item" style="display: flex; align-items: flex-start; padding: 15px; margin-bottom: 10px; background: white; border-radius: 6px; border-left: 4px solid <?php 
            echo $recommendation['type'] === 'error' ? '#e74c3c' : ($recommendation['type'] === 'warning' ? '#f39c12' : '#3498db');
        ?>;">
            <div style="font-size: 20px; margin-right: 15px;">
                <?php echo $recommendation['type'] === 'error' ? '❌' : ($recommendation['type'] === 'warning' ? '⚠️' : 'ℹ️'); ?>
            </div>
            <div style="flex: 1;">
                <div style="font-weight: 600; margin-bottom: 5px; color: #2c3e50;"><?php echo htmlspecialchars($recommendation['title']); ?></div>
                <div style="font-size: 14px; color: #666;"><?php echo htmlspecialchars($recommendation['message']); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- UA黑名单检查结果 -->
    <div class="ua-check-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🚫 UA黑名单检查</h3>
        
        <?php if ($report['ua_blacklist']['error']): ?>
            <div style="padding: 15px; background: rgba(231,76,60,0.1); border-radius: 6px; color: #e74c3c;">
                ❌ <?php echo htmlspecialchars($report['ua_blacklist']['error']); ?>
            </div>
        <?php else: ?>
            <!-- UA统计信息 -->
            <div class="ua-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(52,152,219,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #3498db;"><?php echo $report['ua_blacklist']['stats']['total_lines']; ?></div>
                    <div style="font-size: 12px; color: #666;">总行数</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(231,76,60,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #e74c3c;"><?php echo $report['ua_blacklist']['stats']['mixed_format']; ?></div>
                    <div style="font-size: 12px; color: #666;">混合格式</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(39,174,96,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #27ae60;"><?php echo $report['ua_blacklist']['stats']['pure_ua']; ?></div>
                    <div style="font-size: 12px; color: #666;">纯UA格式</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(243,156,18,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #f39c12;"><?php echo $report['ua_blacklist']['stats']['duplicate_ua']; ?></div>
                    <div style="font-size: 12px; color: #666;">重复UA</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(155,89,182,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #9b59b6;"><?php echo $report['ua_blacklist']['stats']['invalid_lines']; ?></div>
                    <div style="font-size: 12px; color: #666;">无效行</div>
                </div>
            </div>
            
            <!-- 问题条目 -->
            <?php 
            $problemEntries = array_filter($report['ua_blacklist']['entries'], function($entry) {
                return $entry['status'] !== 'valid';
            });
            ?>
            
            <?php if (!empty($problemEntries)): ?>
            <div class="problem-entries" style="max-height: 300px; overflow-y: auto;">
                <h4 style="margin: 0 0 15px 0; color: #e74c3c;">⚠️ 问题条目 (显示前50个)</h4>
                <?php foreach (array_slice($problemEntries, 0, 50) as $entry): ?>
                <div class="problem-entry" style="padding: 10px; margin-bottom: 8px; background: white; border-radius: 4px; border-left: 3px solid <?php echo $entry['status'] === 'invalid' ? '#e74c3c' : '#f39c12'; ?>;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">第 <?php echo $entry['line_number']; ?> 行</div>
                    <div style="font-family: monospace; font-size: 13px; margin-bottom: 5px; word-break: break-all;">
                        <?php echo htmlspecialchars($entry['original']); ?>
                    </div>
                    <div style="font-size: 12px; color: #e74c3c;">
                        <?php echo implode(', ', $entry['issues']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- IP黑名单检查结果 -->
    <div class="ip-check-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🌐 IP黑名单检查</h3>
        
        <?php if ($report['ip_blacklist']['error']): ?>
            <div style="padding: 15px; background: rgba(231,76,60,0.1); border-radius: 6px; color: #e74c3c;">
                ❌ <?php echo htmlspecialchars($report['ip_blacklist']['error']); ?>
            </div>
        <?php else: ?>
            <!-- IP统计信息 -->
            <div class="ip-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(52,152,219,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #3498db;"><?php echo $report['ip_blacklist']['stats']['total_lines']; ?></div>
                    <div style="font-size: 12px; color: #666;">总行数</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(39,174,96,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #27ae60;"><?php echo $report['ip_blacklist']['stats']['valid_ips']; ?></div>
                    <div style="font-size: 12px; color: #666;">有效IP</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(231,76,60,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #e74c3c;"><?php echo $report['ip_blacklist']['stats']['invalid_ips']; ?></div>
                    <div style="font-size: 12px; color: #666;">无效IP</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(243,156,18,0.1); border-radius: 6px;">
                    <div style="font-size: 20px; font-weight: bold; color: #f39c12;"><?php echo $report['ip_blacklist']['stats']['duplicate_ips']; ?></div>
                    <div style="font-size: 12px; color: #666;">重复IP</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 15px; background: rgba(52,73,94,0.1); border-radius: 6px;">
                    <div style="font-size: 16px; font-weight: bold; color: #34495e;"><?php echo $report['ip_blacklist']['stats']['ipv4_count']; ?>/<?php echo $report['ip_blacklist']['stats']['ipv6_count']; ?></div>
                    <div style="font-size: 12px; color: #666;">IPv4/IPv6</div>
                </div>
            </div>
            
            <!-- 问题条目 -->
            <?php 
            $problemIPs = array_filter($report['ip_blacklist']['entries'], function($entry) {
                return $entry['status'] !== 'valid';
            });
            ?>
            
            <?php if (!empty($problemIPs)): ?>
            <div class="problem-ips" style="max-height: 300px; overflow-y: auto;">
                <h4 style="margin: 0 0 15px 0; color: #e74c3c;">⚠️ 问题IP (显示前50个)</h4>
                <?php foreach (array_slice($problemIPs, 0, 50) as $entry): ?>
                <div class="problem-entry" style="padding: 10px; margin-bottom: 8px; background: white; border-radius: 4px; border-left: 3px solid <?php echo $entry['status'] === 'invalid' ? '#e74c3c' : '#f39c12'; ?>;">
                    <div style="font-size: 12px; color: #666; margin-bottom: 5px;">第 <?php echo $entry['line_number']; ?> 行</div>
                    <div style="font-family: monospace; font-size: 13px; margin-bottom: 5px;">
                        <?php echo htmlspecialchars($entry['ip']); ?>
                    </div>
                    <div style="font-size: 12px; color: #e74c3c;">
                        <?php echo implode(', ', $entry['issues']); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.stat-box:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-box {
    transition: all 0.3s ease;
}

.problem-entry:hover {
    background: rgba(52,152,219,0.05) !important;
}

.recommendation-item:hover {
    transform: translateX(5px);
}

.recommendation-item {
    transition: all 0.3s ease;
}
</style>

<script>
// 自动刷新检查结果
function refreshCheck() {
    window.location.reload();
}

// 导出检查报告
function exportReport() {
    const report = <?php echo json_encode($report); ?>;
    const content = JSON.stringify(report, null, 2);
    const blob = new Blob([content], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'blacklist_check_report_' + new Date().toISOString().slice(0, 10) + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// 添加导出按钮
document.addEventListener('DOMContentLoaded', function() {
    const header = document.querySelector('.blacklist-check-page h2').parentElement;
    const exportBtn = document.createElement('button');
    exportBtn.textContent = '📊 导出报告';
    exportBtn.className = 'btn btn-info';
    exportBtn.style.marginLeft = '10px';
    exportBtn.onclick = exportReport;
    header.appendChild(exportBtn);
});
</script>

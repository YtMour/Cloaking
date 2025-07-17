<?php
$title = 'IP测试工具 - Cloak 管理后台';
$module = 'tools';
?>

<div class="ip-test-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #2c3e50;">🌐 IP 测试工具</h2>
        <a href="?module=tools" class="btn btn-secondary" style="text-decoration: none;">← 返回工具页面</a>
    </div>

    <!-- 单个IP测试 -->
    <div class="test-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🔍 单个IP测试</h3>
        <form method="post">
            <div class="form-group">
                <label>IP 地址</label>
                <input type="text" name="ip_address" placeholder="输入要测试的IP地址 (支持IPv4和IPv6)" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div class="form-group">
                <label>User-Agent (可选)</label>
                <input type="text" name="user_agent" placeholder="留空使用默认UA" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <button type="submit" name="test_ip" class="btn btn-primary">开始测试</button>
        </form>
    </div>

    <!-- 批量测试 -->
    <div class="batch-test-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">📊 批量测试</h3>
        <form method="post">
            <div class="form-group">
                <label>测试类型</label>
                <div style="margin-top: 10px;">
                    <label style="display: inline-block; margin-right: 20px;">
                        <input type="radio" name="test_type" value="common" checked> 常见IP测试 (DNS、云服务商、机器人)
                    </label>
                    <label style="display: inline-block;">
                        <input type="radio" name="test_type" value="blacklist"> 黑名单样本测试 (前20个)
                    </label>
                </div>
            </div>
            
            <button type="submit" name="batch_test_ip" class="btn btn-success">批量测试</button>
        </form>
    </div>

    <!-- 测试结果 -->
    <?php if (!empty($test_results)): ?>
    <div class="results-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; color: #2c3e50;">📋 测试结果</h3>
            <button onclick="exportResults()" class="btn btn-info">导出结果</button>
        </div>

        <!-- 结果统计 -->
        <?php 
        $totalTests = count($test_results);
        $blockedCount = array_sum(array_column($test_results, 'is_blocked'));
        $passedCount = $totalTests - $blockedCount;
        $blockRate = $totalTests > 0 ? round(($blockedCount / $totalTests) * 100, 1) : 0;
        $validIPs = array_sum(array_column($test_results, 'is_valid_ip'));
        $ipv4Count = 0;
        $ipv6Count = 0;
        foreach ($test_results as $result) {
            if ($result['ip_type'] === 'IPv4') $ipv4Count++;
            elseif ($result['ip_type'] === 'IPv6') $ipv6Count++;
        }
        ?>
        
        <div class="stats-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(52,152,219,0.1); border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #3498db;"><?php echo $totalTests; ?></div>
                <div style="font-size: 12px; color: #666;">总测试数</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(231,76,60,0.1); border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #e74c3c;"><?php echo $blockedCount; ?></div>
                <div style="font-size: 12px; color: #666;">被拦截</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(39,174,96,0.1); border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo $passedCount; ?></div>
                <div style="font-size: 12px; color: #666;">通过</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(243,156,18,0.1); border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #f39c12;"><?php echo $blockRate; ?>%</div>
                <div style="font-size: 12px; color: #666;">拦截率</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(155,89,182,0.1); border-radius: 8px;">
                <div style="font-size: 24px; font-weight: bold; color: #9b59b6;"><?php echo $validIPs; ?></div>
                <div style="font-size: 12px; color: #666;">有效IP</div>
            </div>
            <div class="stat-item" style="text-align: center; padding: 15px; background: rgba(52,73,94,0.1); border-radius: 8px;">
                <div style="font-size: 18px; font-weight: bold; color: #34495e;"><?php echo $ipv4Count; ?>/<?php echo $ipv6Count; ?></div>
                <div style="font-size: 12px; color: #666;">IPv4/IPv6</div>
            </div>
        </div>

        <!-- 结果列表 -->
        <div class="results-list" style="max-height: 500px; overflow-y: auto;">
            <?php foreach ($test_results as $index => $result): ?>
            <div class="result-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; margin-bottom: 10px; background: white; border-radius: 6px; border-left: 4px solid <?php echo !$result['is_valid_ip'] ? '#95a5a6' : ($result['is_blocked'] ? '#e74c3c' : '#27ae60'); ?>;">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-family: monospace; font-size: 14px; font-weight: 600; margin-bottom: 8px;">
                        <?php echo htmlspecialchars($result['ip']); ?>
                        <span style="font-size: 12px; color: #666; margin-left: 10px;">(<?php echo $result['ip_type']; ?>)</span>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <span style="margin-right: 15px;"><strong>时间:</strong> <?php echo $result['timestamp']; ?></span>
                        <?php if (isset($result['is_private']) && $result['is_private']): ?>
                            <span style="color: #f39c12; margin-right: 10px;">内网IP</span>
                        <?php endif; ?>
                        <?php if (isset($result['is_cloud_provider']) && $result['is_cloud_provider']): ?>
                            <span style="color: #9b59b6; margin-right: 10px;">云服务商</span>
                        <?php endif; ?>
                        <?php if (isset($result['ua_match']) && isset($result['ip_match'])): ?>
                            <?php if ($result['ua_match']): ?>
                                <span style="color: #e74c3c; margin-right: 10px;">UA匹配</span>
                            <?php endif; ?>
                            <?php if ($result['ip_match']): ?>
                                <span style="color: #e74c3c; margin-right: 10px;">IP匹配</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="margin-left: 15px; text-align: center;">
                    <?php if (!$result['is_valid_ip']): ?>
                        <div style="padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; color: #95a5a6; background: rgba(149, 165, 166, 0.2);">
                            ❌ 无效IP
                        </div>
                    <?php else: ?>
                        <div style="padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; <?php echo $result['is_blocked'] ? 'color: #e74c3c; background: rgba(231, 76, 60, 0.2);' : 'color: #27ae60; background: rgba(39, 174, 96, 0.2);'; ?>">
                            <?php echo $result['is_blocked'] ? '🚫 被拦截' : '✅ 通过'; ?>
                        </div>
                    <?php endif; ?>
                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                        <?php echo htmlspecialchars($result['block_reason']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 常见IP示例 -->
    <div class="examples-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">📝 常见IP示例</h3>
        
        <div class="ip-categories" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <!-- 公共DNS -->
            <div class="ip-category">
                <h4 style="margin: 0 0 10px 0; color: #3498db;">🌐 公共DNS</h4>
                <div class="ip-examples" style="font-size: 13px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">8.8.8.8</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">1.1.1.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">208.67.222.222</div>
                </div>
            </div>
            
            <!-- 云服务商 -->
            <div class="ip-category">
                <h4 style="margin: 0 0 10px 0; color: #e74c3c;">☁️ 云服务商</h4>
                <div class="ip-examples" style="font-size: 13px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">3.80.0.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">35.184.0.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">47.88.0.1</div>
                </div>
            </div>
            
            <!-- 机器人IP -->
            <div class="ip-category">
                <h4 style="margin: 0 0 10px 0; color: #f39c12;">🤖 机器人IP</h4>
                <div class="ip-examples" style="font-size: 13px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">66.249.66.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">40.77.167.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">173.252.127.1</div>
                </div>
            </div>
            
            <!-- 内网IP -->
            <div class="ip-category">
                <h4 style="margin: 0 0 10px 0; color: #27ae60;">🏠 内网IP</h4>
                <div class="ip-examples" style="font-size: 13px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">192.168.1.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">10.0.0.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillIP(this.textContent)">127.0.0.1</div>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 15px; font-size: 13px; color: #666;">
            💡 <strong>提示：</strong>点击上面的IP示例可以自动填入测试框
        </div>
    </div>
</div>

<style>
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 14px;
}

.result-item:hover {
    transform: translateX(5px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.result-item {
    transition: all 0.3s ease;
}

.ip-examples div:hover {
    background: rgba(52,152,219,0.1);
    padding: 2px 4px;
    border-radius: 3px;
}
</style>

<script>
// 填充IP到测试框
function fillIP(ip) {
    document.querySelector('input[name="ip_address"]').value = ip;
    document.querySelector('input[name="ip_address"]').focus();
}

// 导出测试结果
function exportResults() {
    const results = <?php echo json_encode($test_results ?? []); ?>;
    if (results.length === 0) {
        alert('没有测试结果可导出');
        return;
    }
    
    let csv = "时间,IP地址,IP类型,是否有效,是否拦截,拦截原因,是否内网,是否云服务商\n";
    results.forEach(result => {
        csv += `"${result.timestamp}","${result.ip}","${result.ip_type}","${result.is_valid_ip ? '是' : '否'}","${result.is_blocked ? '是' : '否'}","${result.block_reason.replace(/"/g, '""')}","${result.is_private ? '是' : '否'}","${result.is_cloud_provider ? '是' : '否'}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'ip_test_results_' + new Date().toISOString().slice(0, 10) + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

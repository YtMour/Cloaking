<?php
$title = 'UA测试工具 - Cloak 管理后台';
$module = 'tools';
?>

<div class="ua-test-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #2c3e50;">🧪 User-Agent 测试工具</h2>
        <a href="?module=tools" class="btn btn-secondary" style="text-decoration: none;">← 返回工具页面</a>
    </div>

    <!-- 单个UA测试 -->
    <div class="test-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🔍 单个UA测试</h3>
        <form method="post">
            <div class="form-group">
                <label>User-Agent 字符串</label>
                <input type="text" name="user_agent" placeholder="输入要测试的User-Agent" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <div class="form-group">
                <label>测试IP地址 (可选)</label>
                <input type="text" name="test_ip" placeholder="留空使用当前IP" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
            </div>
            
            <button type="submit" name="test_ua" class="btn btn-primary">开始测试</button>
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
                        <input type="radio" name="test_type" value="common" checked> 常见UA测试 (浏览器、机器人、工具)
                    </label>
                    <label style="display: inline-block;">
                        <input type="radio" name="test_type" value="blacklist"> 黑名单样本测试 (前20个)
                    </label>
                </div>
            </div>
            
            <button type="submit" name="batch_test" class="btn btn-success">批量测试</button>
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
        ?>
        
        <div class="stats-summary" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 25px;">
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
        </div>

        <!-- 结果列表 -->
        <div class="results-list" style="max-height: 500px; overflow-y: auto;">
            <?php foreach ($test_results as $index => $result): ?>
            <div class="result-item" style="display: flex; justify-content: space-between; align-items: center; padding: 15px; margin-bottom: 10px; background: white; border-radius: 6px; border-left: 4px solid <?php echo $result['is_blocked'] ? '#e74c3c' : '#27ae60'; ?>;">
                <div style="flex: 1; min-width: 0;">
                    <div style="font-family: monospace; font-size: 13px; word-break: break-all; margin-bottom: 8px;">
                        <?php echo htmlspecialchars($result['user_agent']); ?>
                    </div>
                    <div style="font-size: 12px; color: #666;">
                        <span style="margin-right: 15px;"><strong>测试IP:</strong> <?php echo htmlspecialchars($result['test_ip']); ?></span>
                        <span style="margin-right: 15px;"><strong>时间:</strong> <?php echo $result['timestamp']; ?></span>
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
                    <div style="padding: 6px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; <?php echo $result['is_blocked'] ? 'color: #e74c3c; background: rgba(231, 76, 60, 0.2);' : 'color: #27ae60; background: rgba(39, 174, 96, 0.2);'; ?>">
                        <?php echo $result['is_blocked'] ? '🚫 被拦截' : '✅ 通过'; ?>
                    </div>
                    <div style="font-size: 11px; color: #666; margin-top: 4px;">
                        <?php echo htmlspecialchars($result['block_reason']); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 常见UA示例 -->
    <div class="examples-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">📝 常见UA示例</h3>
        
        <div class="ua-categories" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- 浏览器UA -->
            <div class="ua-category">
                <h4 style="margin: 0 0 10px 0; color: #3498db;">🌐 浏览器</h4>
                <div class="ua-examples" style="font-size: 12px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X)</div>
                </div>
            </div>
            
            <!-- 机器人UA -->
            <div class="ua-category">
                <h4 style="margin: 0 0 10px 0; color: #e74c3c;">🤖 机器人</h4>
                <div class="ua-examples" style="font-size: 12px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">Googlebot/2.1 (+http://www.google.com/bot.html)</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">facebookexternalhit/1.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">Twitterbot/1.0</div>
                </div>
            </div>
            
            <!-- 工具UA -->
            <div class="ua-category">
                <h4 style="margin: 0 0 10px 0; color: #f39c12;">🔧 工具</h4>
                <div class="ua-examples" style="font-size: 12px; font-family: monospace; color: #666;">
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">curl/7.68.0</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">python-requests/2.28.1</div>
                    <div style="margin-bottom: 5px; cursor: pointer;" onclick="fillUA(this.textContent)">PostmanRuntime/7.29.2</div>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 15px; font-size: 13px; color: #666;">
            💡 <strong>提示：</strong>点击上面的UA示例可以自动填入测试框
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

.ua-examples div:hover {
    background: rgba(52,152,219,0.1);
    padding: 2px 4px;
    border-radius: 3px;
}
</style>

<script>
// 填充UA到测试框
function fillUA(ua) {
    document.querySelector('input[name="user_agent"]').value = ua;
    document.querySelector('input[name="user_agent"]').focus();
}

// 导出测试结果
function exportResults() {
    const results = <?php echo json_encode($test_results ?? []); ?>;
    if (results.length === 0) {
        alert('没有测试结果可导出');
        return;
    }
    
    let csv = "时间,User-Agent,测试IP,是否拦截,拦截原因\n";
    results.forEach(result => {
        csv += `"${result.timestamp}","${result.user_agent.replace(/"/g, '""')}","${result.test_ip}","${result.is_blocked ? '是' : '否'}","${result.block_reason.replace(/"/g, '""')}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'ua_test_results_' + new Date().toISOString().slice(0, 10) + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php
$title = '系统工具 - Cloak 管理后台';
$module = 'tools';
?>

<div class="tools-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0; color: #2c3e50;">🔧 系统工具</h2>
        <div style="font-size: 0.9rem; color: #666;">
            系统维护和测试工具集
        </div>
    </div>

    <!-- 工具导航 -->
    <div class="tools-nav" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <a href="?module=tools&subpage=blacklist_check" class="tool-card" style="text-decoration: none; color: inherit;">
            <div style="background: linear-gradient(135deg, #3498db, #2980b9); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(52,152,219,0.3); transition: transform 0.3s ease;">
                <div style="font-size: 2rem; margin-bottom: 15px;">🔍</div>
                <h3 style="margin: 0 0 10px 0;">黑名单检查</h3>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">检查黑名单文件的完整性和格式，发现重复项和错误</p>
            </div>
        </a>
        
        <a href="?module=tools&subpage=ua_test" class="tool-card" style="text-decoration: none; color: inherit;">
            <div style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(231,76,60,0.3); transition: transform 0.3s ease;">
                <div style="font-size: 2rem; margin-bottom: 15px;">🧪</div>
                <h3 style="margin: 0 0 10px 0;">UA 测试工具</h3>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">测试不同User-Agent是否会被黑名单拦截</p>
            </div>
        </a>
        
        <a href="?module=tools&subpage=ip_test" class="tool-card" style="text-decoration: none; color: inherit;">
            <div style="background: linear-gradient(135deg, #27ae60, #229954); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(39,174,96,0.3); transition: transform 0.3s ease;">
                <div style="font-size: 2rem; margin-bottom: 15px;">🌐</div>
                <h3 style="margin: 0 0 10px 0;">IP 测试工具</h3>
                <p style="margin: 0; opacity: 0.9; font-size: 14px;">测试不同IP地址是否会被黑名单拦截</p>
            </div>
        </a>
    </div>

    <!-- 系统维护工具 -->
    <div class="maintenance-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 25px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🛠️ 系统维护</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <!-- 清理日志 -->
            <div class="maintenance-item">
                <h4 style="margin: 0 0 10px 0; color: #e74c3c;">📝 清理日志</h4>
                <p style="margin: 0 0 15px 0; font-size: 14px; color: #666;">清空系统日志文件，释放磁盘空间</p>
                <form method="post" style="display: inline;">
                    <button type="submit" name="clear_logs" class="btn btn-danger" 
                            onclick="return confirm('确定要清理日志吗？当前日志将被备份。')">清理日志</button>
                </form>
            </div>
            
            <!-- 备份数据 -->
            <div class="maintenance-item">
                <h4 style="margin: 0 0 10px 0; color: #3498db;">💾 备份数据</h4>
                <p style="margin: 0 0 15px 0; font-size: 14px; color: #666;">备份所有重要数据文件</p>
                <form method="post" style="display: inline;">
                    <button type="submit" name="backup_data" class="btn btn-primary">立即备份</button>
                </form>
            </div>
            
            <!-- 清理黑名单 -->
            <div class="maintenance-item">
                <h4 style="margin: 0 0 10px 0; color: #f39c12;">🧹 清理黑名单</h4>
                <p style="margin: 0 0 15px 0; font-size: 14px; color: #666;">移除重复项和空行</p>
                <form method="post" style="display: inline;">
                    <select name="clean_type" style="margin-right: 10px; padding: 6px;">
                        <option value="ua">UA黑名单</option>
                        <option value="ip">IP黑名单</option>
                    </select>
                    <label style="font-size: 12px; margin-right: 10px;">
                        <input type="checkbox" name="remove_empty" checked> 移除空行
                    </label>
                    <label style="font-size: 12px; margin-right: 10px;">
                        <input type="checkbox" name="remove_duplicates" checked> 移除重复
                    </label>
                    <button type="submit" name="clean_blacklist" class="btn btn-warning">清理</button>
                </form>
            </div>
        </div>
    </div>

    <!-- 系统信息 -->
    <div class="system-info-section" style="background: rgba(255,255,255,0.8); padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">💻 系统信息</h3>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <!-- 服务器信息 -->
            <div class="info-group">
                <h4 style="margin: 0 0 15px 0; color: #3498db;">🖥️ 服务器环境</h4>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">PHP版本</td><td style="font-family: monospace;"><?php echo $system_info['php_version']; ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">服务器软件</td><td style="font-family: monospace;"><?php echo htmlspecialchars($system_info['server_software']); ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">时区</td><td style="font-family: monospace;"><?php echo $system_info['timezone']; ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">当前时间</td><td style="font-family: monospace;"><?php echo $system_info['current_time']; ?></td></tr>
                </table>
            </div>
            
            <!-- 内存使用 -->
            <div class="info-group">
                <h4 style="margin: 0 0 15px 0; color: #e74c3c;">📊 内存使用</h4>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">当前使用</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['memory_usage']); ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">峰值使用</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['memory_peak']); ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">可用磁盘</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['disk_free']); ?></td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">总磁盘</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['disk_total']); ?></td></tr>
                </table>
            </div>
            
            <!-- 文件信息 -->
            <div class="info-group">
                <h4 style="margin: 0 0 15px 0; color: #27ae60;">📁 文件统计</h4>
                <table style="width: 100%; font-size: 13px;">
                    <tr><td style="padding: 4px 0; color: #666;">UA黑名单</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['ua_blacklist_size']); ?> (<?php echo $system_info['ua_blacklist_lines']; ?> 行)</td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">IP黑名单</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['ip_blacklist_size']); ?> (<?php echo $system_info['ip_blacklist_lines']; ?> 行)</td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">系统日志</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['log_file_size']); ?> (<?php echo $system_info['log_file_lines']; ?> 行)</td></tr>
                    <tr><td style="padding: 4px 0; color: #666;">API配置</td><td style="font-family: monospace;"><?php echo formatBytes($system_info['api_config_size']); ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.tool-card:hover div {
    transform: translateY(-5px);
}

.maintenance-item {
    padding: 20px;
    background: rgba(255,255,255,0.5);
    border-radius: 8px;
    border-left: 4px solid #3498db;
}

.info-group {
    padding: 20px;
    background: rgba(255,255,255,0.5);
    border-radius: 8px;
    border-left: 4px solid #27ae60;
}

.info-group table td:first-child {
    width: 40%;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
.btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
.btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }

.btn:hover { transform: translateY(-2px); }
</style>

<script>
// 自动刷新系统信息
setInterval(function() {
    // 可以在这里添加AJAX刷新系统信息的代码
}, 30000); // 30秒刷新一次

// 显示操作确认
function confirmAction(message) {
    return confirm(message);
}
</script>



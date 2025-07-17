# 🎯 Cloaking 项目重构设计文档

## 📋 项目概述

本文档详细描述了 Cloaking 流量过滤系统的重构方案，旨在解决当前项目结构不理想、文件过大、代码耦合度高等问题。

### 🎯 重构目标

1. **模块化设计** - 将大型文件拆分为功能独立的小模块
2. **结构优化** - 使用二级文件夹整理非核心文件
3. **代码解耦** - 降低模块间依赖，提高可维护性
4. **性能优化** - 减少单文件复杂度，提高加载效率
5. **扩展性增强** - 便于后续功能扩展和维护

## 📁 当前项目结构分析

### 🔍 现有问题
- `admin_monitor.php` 文件过大（1225行）
- 功能耦合度高，难以维护
- 缺乏模块化设计
- 文件组织混乱

### 📊 文件大小统计
| 文件名 | 行数 | 状态 |
|--------|------|------|
| admin_monitor.php | 1225 | ❌ 需要拆分 |
| check_blacklist.php | 486 | ⚠️ 需要优化 |
| admin_core.php | 387 | ⚠️ 需要模块化 |
| blacklist_operations.php | 277 | ✅ 可接受 |
| log_manager.php | 241 | ✅ 可接受 |

## 🏗️ 新项目结构设计

### 📁 根目录（核心文件）
```
/
├── Cloak_index.php              # 🎯 主入口文件（流量检测和跳转）
├── Cloak_admin.php              # 🔧 管理后台入口（简化版）
├── fake_page.html              # 🎭 机器人看到的假页面
├── LICENSE                     # 📄 许可证文件
└── PROJECT_RESTRUCTURE_DESIGN.md # 📋 本设计文档
```

### 📁 二级文件夹结构

#### � `Cloak_Data/` - 数据文件目录
```
Cloak_Data/
├── ua_blacklist.txt            # 🛡️ UA黑名单数据
├── ip_blacklist.txt            # 🚫 IP黑名单数据
├── log.txt                     # 📊 访问日志文件
├── real_landing_url.txt        # 📝 跳转地址配置
├── api_config.json             # ⚙️ API配置文件
├── backup/                     # 📦 备份目录
│   ├── ua_blacklist_backup.txt
│   ├── ip_blacklist_backup.txt
│   └── config_backup.json
└── logs/                       # 📊 日志归档目录
    ├── access_2024-01.log
    ├── access_2024-02.log
    └── error.log
```

#### �🔧 `Cloak_Core/` - 核心功能模块
```
Cloak_Core/
├── Config.php                  # 配置管理类
├── IPDetector.php              # IP检测模块
├── BlacklistChecker.php        # 黑名单检查模块
├── Logger.php                  # 日志记录模块
├── Auth.php                    # 认证模块
├── Utils.php                   # 工具函数集合
└── Autoloader.php              # 自动加载器
```

#### 🎨 `Cloak_Admin/` - 管理界面模块
```
Cloak_Admin/
├── Controllers/                # 控制器层
│   ├── DashboardController.php
│   ├── UAController.php
│   ├── IPController.php
│   ├── MonitorController.php
│   └── ToolsController.php
├── Models/                     # 数据模型层
│   ├── BlacklistModel.php
│   ├── LogModel.php
│   └── StatisticsModel.php
├── Views/                      # 视图模板层
│   ├── Templates/
│   │   ├── header.php
│   │   ├── footer.php
│   │   ├── navigation.php
│   │   └── styles.php
│   ├── dashboard.php
│   ├── ua_manager.php
│   ├── ip_manager.php
│   └── monitor.php
└── Assets/                     # 静态资源
    ├── css/
    ├── js/
    └── images/
```

#### 🔍 `Cloak_Tools/` - 工具和测试模块
```
Cloak_Tools/
├── BlacklistChecker.php        # 黑名单完整性检查
├── UATest.php                  # UA测试工具
├── IPTest.php                  # IP测试工具
├── SystemTest.php              # 系统测试工具
├── DataExporter.php            # 数据导出工具
├── ConfigValidator.php         # 配置验证工具
└── PerformanceTest.php         # 性能测试工具
```

#### 📊 `Cloak_Monitor/` - 监控和统计模块
```
Cloak_Monitor/
├── LogAnalyzer.php             # 日志分析器
├── StatisticsGenerator.php     # 统计生成器
├── RealtimeMonitor.php         # 实时监控
├── AlertManager.php            # 告警管理
├── ReportGenerator.php         # 报告生成器
└── MetricsCollector.php        # 指标收集器
```

#### 🔄 `Cloak_API/` - API和自动化模块
```
Cloak_API/
├── APIManager.php              # API管理器
├── AutoUpdater.php             # 自动更新器
├── WebhookHandler.php          # Webhook处理器
├── BatchProcessor.php          # 批量处理器
└── EndpointRouter.php          # API路由器
```

#### 📦 `Cloak_Backup/` - 备份和恢复模块
```
Cloak_Backup/
├── BackupManager.php           # 备份管理器
├── RestoreManager.php          # 恢复管理器
├── ConfigMigrator.php          # 配置迁移器
└── DataArchiver.php            # 数据归档器
```

## 🔧 入口文件设计

### 1. 主入口文件 (`Cloak_index.php`)
```php
<?php
/**
 * Cloaking 系统主入口文件
 * 负责流量检测、黑名单过滤和跳转逻辑
 */

require_once 'Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\IPDetector;
use Cloak\Core\BlacklistChecker;
use Cloak\Core\Logger;

// 初始化配置
$config = Config::getInstance();

// 获取访客真实IP
$ipDetector = new IPDetector();
$ip = $ipDetector->getRealIP();

// 获取User-Agent
$ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$referer = $_SERVER['HTTP_REFERER'] ?? '-';

// 黑名单检查
$blacklistChecker = new BlacklistChecker();
$isBot = $blacklistChecker->isBot($ua, $ip);

// 日志记录
$logger = new Logger();

if ($isBot) {
    // 机器人 - 显示假页面
    $logger->log($ip, $ua, '显示假页面 (机器人检测)', $referer);
    include('fake_page.html');
    exit;
}

// 真实用户 - 跳转
$realUrl = $config->getLandingURL();
$logger->log($ip, $ua, '正常跳转', $referer);
header("Location: $realUrl");
exit;
?>
```

### 2. 管理后台入口 (`Cloak_admin.php`)
```php
<?php
/**
 * Cloaking 系统管理后台入口
 * 提供模块化的管理界面路由
 */

require_once 'Cloak_Core/Autoloader.php';

use Cloak\Admin\Controllers\AdminController;
use Cloak\Core\Config;

// 设置数据路径
define('CLOAK_DATA_PATH', 'Cloak_Data/');

// 初始化管理控制器
$controller = new AdminController();

// 处理请求
$module = $_GET['module'] ?? 'dashboard';
$controller->route($module);
?>
```

### 3. 部署配置说明

#### 本地部署配置
当您在本地部署静态页面或PHP页面时，`real_landing_url.txt` 的配置方式：

**场景1：本地静态页面**
```
# 如果您的真实页面是 landing.html
./landing.html

# 或者相对路径
../real_site/index.html
```

**场景2：本地PHP页面**
```
# 如果您的真实页面是 welcome.php
./welcome.php

# 或者子目录中的页面
./real_site/index.php
```

**场景3：本地完整URL**
```
# 如果使用完整的本地URL
http://localhost/your_real_site/index.php

# 或者指定端口
http://localhost:8080/landing.php
```

**场景4：外部URL**
```
# 跳转到外部网站
https://www.example.com

# 跳转到其他域名
https://your-real-domain.com/landing
```

#### 推荐的本地部署结构
```
/your_website/
├── Cloak_index.php              # Cloaking入口（伪装成主页）
├── Cloak_admin.php              # 管理后台
├── fake_page.html              # 机器人看到的假页面
├── real_landing.php            # 真实的落地页
├── Cloak_Data/
│   ├── real_landing_url.txt    # 内容：./real_landing.php
│   └── ...
└── Cloak_Core/
    └── ...
```

## 🔧 核心模块设计

### 1. 配置管理模块 (`Cloak_Core/Config.php`)
```php
class Config {
    // 统一配置管理
    // 环境变量支持
    // 配置验证
    // 缓存机制
}
```

### 2. IP检测模块 (`Cloak_Core/IPDetector.php`)
```php
class IPDetector {
    // Cloudflare IP检测
    // 代理IP处理
    // IP验证和过滤
    // 地理位置检测
}
```

### 3. 黑名单检查模块 (`Cloak_Core/BlacklistChecker.php`)
```php
class BlacklistChecker {
    private $dataPath = 'Cloak_Data/';

    public function checkUA($userAgent) {
        $blacklist = file($this->dataPath . 'ua_blacklist.txt');
        // UA黑名单检查逻辑
    }

    public function checkIP($ip) {
        $blacklist = file($this->dataPath . 'ip_blacklist.txt');
        // IP黑名单检查逻辑
    }

    // 混合格式解析
    // 缓存优化
}
```

### 4. 数据管理模块 (`Cloak_Core/DataManager.php`)
```php
class DataManager {
    private $dataPath = 'Cloak_Data/';

    public function getBlacklistPath($type) {
        return $this->dataPath . $type . '_blacklist.txt';
    }

    public function getLogPath() {
        return $this->dataPath . 'log.txt';
    }

    public function getLandingURL() {
        return trim(file_get_contents($this->dataPath . 'real_landing_url.txt'));
    }

    // 数据文件管理
    // 备份和恢复
    // 文件权限检查
}
```

## 📋 admin_monitor.php 拆分方案

### 🎯 拆分策略（1225行 → 多个小模块）

#### 原文件功能分解：
1. **日志显示和分页** (300行) → `Cloak_Monitor/LogAnalyzer.php`
2. **黑名单操作** (250行) → `Cloak_Admin/Controllers/MonitorController.php`
3. **批量操作** (200行) → `Cloak_API/BatchProcessor.php`
4. **统计分析** (180行) → `Cloak_Monitor/StatisticsGenerator.php`
5. **过滤和搜索** (150行) → `Cloak_Monitor/LogAnalyzer.php`
6. **界面模板** (145行) → `Cloak_Admin/Views/monitor.php`

#### 新的模块结构：
```php
// 新的 Cloak_admin.php (简化版 < 100行)
<?php
require_once 'Cloak_Core/Autoloader.php';
require_once 'Cloak_Admin/Controllers/AdminController.php';

// 设置数据路径
define('CLOAK_DATA_PATH', 'Cloak_Data/');

$controller = new AdminController();
$controller->handleRequest();
?>
```

## 🎨 用户界面优化

### 基于用户偏好的改进：
1. **多选批量操作** - 改进的复选框和批量操作按钮
2. **水平按钮布局** - 过滤/重置按钮水平排列
3. **合理的文字大小** - 避免过小的文字，提高可读性
4. **模块化组件** - 可复用的UI组件库

### 响应式设计：
- 移动端适配
- 平板端优化
- 桌面端增强

## 📊 性能优化目标

### 文件大小控制：
| 模块类型 | 目标行数 | 加载时间目标 |
|---------|---------|-------------|
| 核心模块 | < 300行 | < 50ms |
| 控制器 | < 200行 | < 30ms |
| 视图模板 | < 150行 | < 20ms |
| 工具模块 | < 400行 | < 100ms |

### 内存使用优化：
- 延迟加载
- 对象池
- 缓存机制
- 垃圾回收优化

## 🔄 迁移策略

### 阶段1：核心模块重构
1. 创建 `Cloak_Core/` 目录和基础类
2. 创建 `Cloak_Data/` 目录并迁移数据文件
3. 重构 `admin_core.php` 为模块化结构
4. 创建 `Cloak_index.php` 和 `Cloak_admin.php`
5. 更新入口文件使用新的核心模块和数据路径
6. 移除原 `index.php`（避免与正常网站冲突）

### 阶段2：管理界面重构
1. 创建 `Cloak_Admin/` 目录结构
2. 拆分 `admin_monitor.php` 为多个模块
3. 实现新的控制器和视图结构

### 阶段3：工具模块迁移
1. 迁移测试工具到 `Cloak_Tools/`
2. 优化现有工具代码
3. 添加新的测试和验证工具

### 阶段4：监控和API模块
1. 实现 `Cloak_Monitor/` 监控模块
2. 创建 `Cloak_API/` API接口
3. 添加备份和恢复功能

## 🛡️ 向后兼容性

### 兼容性保证：
1. **URL兼容** - 原有管理页面URL继续工作
2. **数据兼容** - 现有数据文件格式保持不变
3. **配置兼容** - 自动检测和迁移旧配置
4. **API兼容** - 保持现有API接口不变

### 迁移辅助：
- 自动检测新旧结构
- 配置迁移向导
- 数据备份和恢复
- 回滚机制

## 📈 扩展性设计

### 插件系统：
- 模块化插件架构
- 钩子和过滤器系统
- 第三方集成接口

### 配置系统：
- 环境变量支持
- 多环境配置
- 动态配置更新

## 🔍 测试策略

### 单元测试：
- 核心模块测试覆盖率 > 90%
- 自动化测试流程
- 持续集成支持

### 集成测试：
- 端到端测试
- 性能测试
- 安全测试

## 📋 实施计划

### 时间安排：
- **第1周**：核心模块重构
- **第2周**：管理界面重构  
- **第3周**：工具模块迁移
- **第4周**：测试和优化

### 里程碑：
1. ✅ 核心模块完成
2. ✅ 管理界面重构完成
3. ✅ 工具模块迁移完成
4. ✅ 全面测试通过
5. ✅ 文档更新完成

## 🔧 详细实施指南

### 核心类设计示例

#### Config.php 设计
```php
<?php
namespace Cloak\Core;

class Config {
    private static $instance = null;
    private $config = [];

    // 数据文件路径配置
    const DATA_PATH = 'Cloak_Data/';
    const UA_BLACKLIST = self::DATA_PATH . 'ua_blacklist.txt';
    const IP_BLACKLIST = self::DATA_PATH . 'ip_blacklist.txt';
    const LOG_FILE = self::DATA_PATH . 'log.txt';
    const LANDING_URL = self::DATA_PATH . 'real_landing_url.txt';
    const API_CONFIG = self::DATA_PATH . 'api_config.json';

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function set($key, $value) {
        $this->config[$key] = $value;
    }

    public function getDataPath($file = '') {
        return self::DATA_PATH . $file;
    }

    public function loadFromFile($file) {
        // 加载配置文件逻辑
    }
}
```

#### IPDetector.php 设计
```php
<?php
namespace Cloak\Core;

class IPDetector {
    private $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    public function getRealIP() {
        foreach ($this->headers as $header) {
            $ip = $this->extractValidIP($header);
            if ($ip) return $ip;
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function extractValidIP($header) {
        // IP提取和验证逻辑
    }
}
```

### 管理界面重构示例

#### MonitorController.php 设计
```php
<?php
namespace Cloak\Admin\Controllers;

class MonitorController extends BaseController {
    private $logAnalyzer;
    private $statsGenerator;

    public function __construct() {
        parent::__construct();
        $this->logAnalyzer = new \Cloak\Monitor\LogAnalyzer();
        $this->statsGenerator = new \Cloak\Monitor\StatisticsGenerator();
    }

    public function index() {
        $page = $_GET['page'] ?? 1;
        $filters = $this->getFilters();

        $logs = $this->logAnalyzer->getLogs($page, 50, $filters);
        $stats = $this->statsGenerator->getStats();

        $this->render('monitor', compact('logs', 'stats'));
    }

    public function batchOperation() {
        $processor = new \Cloak\API\BatchProcessor();
        return $processor->handle($_POST);
    }
}
```

## 📁 文件迁移映射表

### 现有文件 → 新结构映射

| 原文件 | 新位置 | 拆分说明 |
|--------|--------|----------|
| `index.php` | `Cloak_index.php` | 主入口重命名（避免冲突） |
| `admin.php` | `Cloak_admin.php` | 管理入口重命名 |
| `admin_core.php` | `Cloak_Core/Auth.php` + `Cloak_Admin/Controllers/BaseController.php` | 认证逻辑分离 |
| `admin_monitor.php` | `Cloak_Admin/Controllers/MonitorController.php` + `Cloak_Monitor/LogAnalyzer.php` | 功能模块化 |
| `blacklist_operations.php` | `Cloak_Admin/Models/BlacklistModel.php` | 数据模型分离 |
| `log_manager.php` | `Cloak_Monitor/LogAnalyzer.php` | 监控模块整合 |
| `check_blacklist.php` | `Cloak_Tools/BlacklistChecker.php` | 工具模块迁移 |
| `ua_tester.php` | `Cloak_Tools/UATest.php` | 工具模块迁移 |
| `ip_test.php` | `Cloak_Tools/IPTest.php` | 工具模块迁移 |
| `ua_blacklist.txt` | `Cloak_Data/ua_blacklist.txt` | 数据文件迁移 |
| `ip_blacklist.txt` | `Cloak_Data/ip_blacklist.txt` | 数据文件迁移 |
| `log.txt` | `Cloak_Data/log.txt` | 数据文件迁移 |
| `real_landing_url.txt` | `Cloak_Data/real_landing_url.txt` | 数据文件迁移 |

## 🎯 代码质量标准

### 编码规范
- **PSR-4** 自动加载标准
- **PSR-12** 编码风格标准
- **命名空间** 使用 `Cloak\` 作为根命名空间
- **类命名** 使用 PascalCase
- **方法命名** 使用 camelCase
- **常量命名** 使用 UPPER_CASE

### 文档标准
- 每个类必须有完整的 PHPDoc 注释
- 公共方法必须有参数和返回值说明
- 复杂逻辑必须有行内注释

### 安全标准
- 所有用户输入必须验证和过滤
- 使用预处理语句防止SQL注入
- 实施CSRF保护
- 敏感数据加密存储

## 🔄 数据迁移策略

### 配置文件迁移
```php
// 自动检测和迁移旧配置
class ConfigMigrator {
    public function migrate() {
        if (file_exists('admin_core.php')) {
            $this->extractOldConfig();
            $this->createNewConfig();
            $this->backupOldFiles();
        }
    }
}
```

### 数据文件处理
- `Cloak_Data/ua_blacklist.txt` - 迁移到数据目录，添加格式验证
- `Cloak_Data/ip_blacklist.txt` - 迁移到数据目录，添加格式验证
- `Cloak_Data/log.txt` - 迁移到数据目录，添加日志轮转
- `Cloak_Data/real_landing_url.txt` - 迁移到数据目录，添加URL验证
- `Cloak_Data/api_config.json` - 新增API配置文件
- `Cloak_Data/backup/` - 自动备份目录
- `Cloak_Data/logs/` - 日志归档目录

## 🧪 测试计划

### 单元测试覆盖
```
Cloak_Core/
├── ConfigTest.php
├── IPDetectorTest.php
├── BlacklistCheckerTest.php
└── LoggerTest.php

Cloak_Admin/
├── Controllers/
│   ├── DashboardControllerTest.php
│   └── MonitorControllerTest.php
└── Models/
    └── BlacklistModelTest.php
```

### 集成测试场景
1. **完整流量检测流程测试**
2. **管理界面功能测试**
3. **批量操作测试**
4. **API接口测试**
5. **性能压力测试**

## 📊 监控和指标

### 性能指标
- 页面加载时间 < 200ms
- 内存使用 < 64MB
- 文件大小 < 300行/文件
- 代码覆盖率 > 80%

### 业务指标
- 黑名单检测准确率 > 99%
- 系统可用性 > 99.9%
- 日志处理能力 > 10000条/分钟

## 🚀 部署指南

### 环境要求
- PHP 7.4+
- 内存 128MB+
- 磁盘空间 100MB+
- Web服务器 (Apache/Nginx)

### 部署步骤
1. **备份现有文件**
2. **创建新目录结构**
3. **迁移核心文件**
4. **更新配置文件**
5. **测试功能完整性**
6. **切换到新版本**

### 回滚计划
- 保留原文件备份
- 快速回滚脚本
- 数据恢复机制

---

## 📋 总结

本重构方案将显著改善项目的：
- **可维护性** - 模块化设计便于维护
- **可扩展性** - 清晰的架构便于功能扩展
- **性能** - 优化的代码结构提升性能
- **安全性** - 统一的安全标准和验证
- **用户体验** - 改进的界面和交互

**实施建议**：建议分阶段实施，每个阶段完成后进行充分测试，确保系统稳定性。

## 📋 最终项目结构总览

### 根目录结构（简洁专业）
```
/
├── Cloak_index.php              # 🎯 主入口文件（流量检测）
├── Cloak_admin.php              # 🔧 管理后台入口
├── fake_page.html              # 🎭 机器人看到的假页面
├── real_landing.php            # 🎯 真实落地页（示例）
├── LICENSE                     # 📄 许可证文件
└── PROJECT_RESTRUCTURE_DESIGN.md # 📋 设计文档
```

### 完整目录结构
```
/
├── Cloak_index.php              # 主入口文件（Cloaking系统）
├── Cloak_admin.php              # 管理后台入口
├── fake_page.html              # 假页面
├── real_landing.php            # 真实落地页（用户自定义）
├── LICENSE                     # 许可证
├── PROJECT_RESTRUCTURE_DESIGN.md # 设计文档
├── Cloak_Data/                 # 数据文件目录
│   ├── ua_blacklist.txt
│   ├── ip_blacklist.txt
│   ├── log.txt
│   ├── real_landing_url.txt
│   ├── api_config.json
│   ├── backup/
│   └── logs/
├── Cloak_Core/                 # 核心功能模块
│   ├── Config.php
│   ├── IPDetector.php
│   ├── BlacklistChecker.php
│   ├── Logger.php
│   ├── Auth.php
│   ├── Utils.php
│   └── Autoloader.php
├── Cloak_Admin/                # 管理界面模块
│   ├── Controllers/
│   ├── Models/
│   ├── Views/
│   └── Assets/
├── Cloak_Tools/                # 工具和测试模块
├── Cloak_Monitor/              # 监控和统计模块
├── Cloak_API/                  # API和自动化模块
└── Cloak_Backup/               # 备份和恢复模块
```

### 🎯 重构优势总结

1. **文件名冲突避免** - 所有文件使用 `Cloak_` 前缀，不与正常网站冲突
2. **模块化设计** - 功能清晰分离，便于维护
3. **部署灵活性** - 可与任何现有网站共存
4. **专业结构** - 企业级项目组织方式
5. **扩展性强** - 便于后续功能添加

## 🚀 实际部署指南

### 部署场景1：与现有网站共存
```
/your_website/
├── index.html                   # 您的正常网站首页
├── about.html                   # 您的正常网站页面
├── contact.php                  # 您的正常网站页面
├── Cloak_index.php              # Cloaking入口（通过特殊链接访问）
├── Cloak_admin.php              # Cloaking管理后台
├── fake_page.html              # 机器人看到的假页面
└── Cloak_Data/
    ├── real_landing_url.txt    # 内容：./index.html 或 ./contact.php
    └── ...
```

### 部署场景2：Cloaking作为主入口
```
/your_website/
├── Cloak_index.php              # 作为网站主入口（伪装成index）
├── real_landing.php            # 真实的业务页面
├── Cloak_admin.php              # 管理后台
├── fake_page.html              # 假页面
└── Cloak_Data/
    ├── real_landing_url.txt    # 内容：./real_landing.php
    └── ...
```

### real_landing_url.txt 配置示例

**本地相对路径：**
```
./welcome.php
./landing/index.html
../main_site/index.php
```

**本地绝对URL：**
```
http://localhost/your_site/landing.php
http://127.0.0.1:8080/welcome.html
```

**外部URL：**
```
https://your-real-domain.com
https://www.example.com/landing
```

### 访问方式
- **正常用户访问** `Cloak_index.php` → 自动跳转到真实页面
- **机器人访问** `Cloak_index.php` → 显示假页面
- **管理后台** `Cloak_admin.php` → 管理界面

---

**注意**：本重构方案需要在实施前进行详细的代码审查和测试，确保不影响现有功能的正常运行。

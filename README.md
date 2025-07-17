# 🛡️ Cloak 智能流量过滤系统

## 🎯 系统简介

Cloak是一个智能的流量过滤系统，能够自动识别和拦截机器人、爬虫等恶意访问，为真实用户提供无缝的跳转体验。

## 📁 目录结构

```
/
├── Cloak_index.php              # 🎯 主入口文件
├── Cloak_admin.php              # 🔧 管理后台入口
├── fake_page.html              # 🎭 假页面
├── Cloak_Data/                 # 📁 数据文件目录
├── Cloak_Core/                 # 📁 核心功能模块
├── Cloak_Admin/                # 📁 管理界面模块
├── Cloak_Tools/                # 📁 工具和测试模块
├── Cloak_Monitor/              # 📁 监控和统计模块
├── Cloak_API/                  # 📁 API和自动化模块
└── Cloak_Backup/               # 📁 备份和恢复模块
```

## 🚀 快速开始

1. **上传文件到服务器**
2. **设置目录权限**：确保 `Cloak_Data/` 目录可写 (755)
3. **访问管理后台**：`http://your-domain.com/Cloak_admin.php`
4. **默认密码**：`123456`（请及时修改）
5. **设置跳转地址**：在仪表板中配置真实的跳转地址

## ⚙️ 配置说明

### 跳转地址设置
在管理后台的仪表板中设置跳转地址：
- 本地文件：`./your_page.html`
- 外部URL：`https://www.example.com`

### 黑名单管理
- **UA黑名单**：`Cloak_Data/ua_blacklist.txt`
- **IP黑名单**：`Cloak_Data/ip_blacklist.txt`

## 🔒 安全建议

1. **修改默认密码**
2. **定期备份数据**
3. **监控访问日志**
4. **更新黑名单规则**

## 📞 技术支持

详细文档请查看：
- `DEPLOYMENT_CHECKLIST.md` - 部署检查清单
- `PROJECT_RESTRUCTURE_DESIGN.md` - 详细设计文档

---
© 2025 Cloak System - 智能流量过滤系统

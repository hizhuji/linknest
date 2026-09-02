# CHANGELOG

### V6.9.0

1. 新增登录用户的标签、收藏与组合筛选：可按标签、格式、大小和收藏状态整理“我的文件”，后台文件管理同步支持标签、收藏和大小范围搜索。
2. 新增用户配额和用量统计：分别支持存储空间、文件数量、每日上传上限；可在后台为单个用户配置、查看和校准，回收站文件继续占用空间，彻底删除后释放。
3. 新增全站配额强制开关与新用户默认额度，升级后默认仅统计，不会中断旧站点上传。
4. 新增受限 API Key：按用户绑定独立权限范围，支持有效期、来源 IP/CIDR、每日请求数、每日流量限制及立即撤销。
5. API Key 仅创建时显示完整值，数据库只保存哈希和前缀；创建、使用、拒绝与撤销均进入管理员审计。旧 `api_token` 暂时兼容上传调用。
6. 数据库升级至 `1010`，新建标签、收藏、配额、用量、API Key 与 API Key 用量表；原有文件、分享、账号和存储对象不迁移、不清空。

### V6.8.0

1. 新增回收站：后台删除与最后一个分享被删除后的文件均先软删除，默认保留 30 天，可恢复。
2. 新增文件历史版本：管理员替换文件时自动创建快照，可在后台恢复任一版本，原分享链接保持不变。
3. 新增管理员审计：记录登录、设置、文件治理、版本恢复、备份状态与维护动作，支持 CSV 导出。
4. 新增密码分享专用失败限频：按分享链接和来源 IP 独立计数，默认 5 次失败后临时锁定 15 分钟。
5. 新增运维中心：真实数据库/存储探测、备份与恢复演练记录、到期数据清理与 CLI 计划任务。
6. 数据库升级至 `1009`。对象物理删除进入可重试清理队列，降低临时存储故障造成的数据处理风险。

### V6.7.1

1. 紧急修复数据库升级页重复声明公共 `random()` 函数导致的致命错误。
2. 同步移除安装页中未使用的重复函数定义，避免以后加载路径变化时再次冲突。
3. 数据库版本保持 `1008`，已经完成数据库升级的网站不需要再次迁移。

### V6.7.0

1. 新增每条分享独立的来源域名白名单/黑名单、空来源策略和客户端拦截词。
2. 新增每 IP 每分钟请求限制、每日/月流量上限、告警记录与公网 HTTPS Webhook 通知。
3. 分段播放按实际请求字节统计流量，访问日志不保存来源 URL 查询参数。
4. 数据库升级至 `1008`，所有限制默认关闭，旧分享升级后行为不变。

### V6.6.0

1. 文件实体与分享链接完成分离，同一文件可创建多个互不影响的短链接。
2. 新增自定义短码、独立密码/期限/次数、单独撤销/恢复和一次性分享。
3. 新增脱敏访问日志与每日下载、预览、请求和流量汇总。
4. 旧 MD5 分享地址继续兼容，数据库由 `1006` 逐步升级到 `1007`。

### V6.5.1

1. 修复文件名在页面和播放器脚本中的跨站脚本风险，并统一上传文件名清理。
2. 文件密码不再直接出现在 URL，改用密码表单和短期签名访问令牌。
3. 数据库升级入口增加管理员登录、POST 和 CSRF 校验，后台登录增加验证码。
4. 默认只信任直连 IP，新增可信代理 IP/CIDR 配置，并加强本地上传目录保护。

### V6.5.0

1. Renamed the maintained project to LinkNest and moved the primary repository to `hizhuji/linknest`.
2. Replaced the legacy default product name across fresh installation, administration, and documentation surfaces.
3. Added an upgrade migration that changes only untouched legacy default site names while preserving administrator-customized titles.
4. Added prominent attribution to the original `netcccyun/pan` source and retained its MIT license and copyright notice.

### V6.4.1

1. Added a raw GitHub update-package source for servers that cannot negotiate TLS with `codeload.github.com`.
2. Added trusted fallback package URLs and automatic retry across update sources.
3. Forced TLS 1.2 for package downloads when supported by the installed cURL library.

### V6.4.0

1. Replaced the hard-coded administrator brand with the website title configured in the dashboard.
2. Added direct Google OAuth 2.0 and Sign in with Apple user login alongside the existing QQ and WeChat providers.
3. Added WebDAV storage for mounting AList, Nextcloud, and other WebDAV-compatible cloud drives.
4. Added separate direct-upload and direct-download capability checks so WebDAV safely uses server-side transfer unless a public download URL is configured.

### V6.3.0

1. Added a maintenance-repository-backed online updater to the administrator dashboard.
2. Added package host restrictions, SHA-256 verification, archive path validation, staging, and pre-update source backups.
3. Preserved site configuration, local uploads, runtime update data, and the installation lock during upgrades.
4. Replaced the discontinued upstream version-check service with the community maintenance channel.

### V6.2.0

1. Added an administrator setting for changing the public site and share-link domain.
2. Unified generated share, download, preview, player, API, QR-code, and login callback URLs around the configured public address.
3. Added URL normalization and validation with automatic host detection as the fallback.

### V6.1.0

1. Added configurable share expiry and maximum access counts for web uploads and the upload API.
2. Enforced access policies consistently across downloads, previews, and embedded players.
3. Added owner renewal controls, administrator editing, and password checks for direct preview links.

### V6.0.1

1. Rebuilt the public file-list homepage as a responsive workspace with streamlined navigation, search, upload entry, empty state, and pagination.

### V6.0.0

1. Community-maintained baseline created from upstream `a4e5a43`.
2. Added signed authentication cookies, secure session cookies, login rate limiting, and CSRF protection for admin mutations.
3. Migrated administrator passwords to `password_hash()` on successful login and removed the fixed installer password.
4. Fixed API origin validation and added optional API-token enforcement.
5. Enabled TLS certificate verification for application-owned HTTP clients.
6. Added upgrade documentation, storage acceptance checklist, CI, and dependency monitoring.

### V5.5

1. 后台支持批量封禁解封
2. 优化后台加载图片速度
3. 修复部分云存储下载中文名乱码

### V5.4

1. 修复一个高危漏洞
2. 修复后台文件搜索等问题

### V5.3

1. 新增用户系统，登录用户可保留上传记录
2. 默认使用分块上传，解决大文件上传失败问题
3. 上传前计算文件hash，支持极速秒传，新增文件完整性校验
4. 云存储支持直接对接接口上传，无需本机中转，上传速度更快
5. 云存储支持直接链接下载模式，下载速度更快
6. 文件下载新增断点续传功能，视频播放可拖拽
7. 新增文件搜索功能
8. 增加七牛云存储
9. 优化文件预览等页面样式

### V5.2

1. 增加又拍云和华为云OBS存储
2. 修复二维码显示
3. 增加上传API接口

### V5.1

1. 增加腾讯云COS存储
2. 修复SAE兼容性问题
3. 修复其他多个问题

### V5.0

1. 全新界面，电脑手机自适应
2. 视频播放器改用ckplayer，音乐播放器改用APlayer
3. 全新的文件类型小图标
4. 支持开启视频文件人工审核
5. 新增阿里云图片违规检测API
6. 所有网站设置均可在后台修改
7. 支持自定义本地存储路径
8. 新增对接阿里云OSS存储

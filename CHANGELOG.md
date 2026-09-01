# CHANGELOG

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

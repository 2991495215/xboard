# s3 部署对应关系

线上基础镜像来自本仓库的 `trent30` 分支。s3 原有源码目录的 Git remote 指向
`cedar2025/Xboard`，不能通过该目录的 `git pull` 获取本仓库改动。

`compose.s3.sample.yaml` 保存现有服务结构，复制为 `compose.yaml` 前需要：

- 准备 XBoard `.env`、数据目录以及独立部署的 EZ THEME 文件
  `storage/theme/Xboard/index.html`、`dashboard.blade.php`、静态资源和法律页面。
- 将 `waffo-bridge/.env.example` 复制为 `waffo-bridge/.env`，填写商户参数和内部令牌。
- 将 Waffo 私钥放在 `waffo-bridge/secrets/private-key.pem`，令牌与面板插件配置保持一致。
- 使用支持 `post_start` 的 Docker Compose；该步骤会替换容器内的公开主题目录。
- 数据库、插件启用状态和商户配置需要单独恢复，不包含在源码中。

示例默认保留已验证可拉取的旧包版本，不代表新同步代码已包含在该镜像中。
改名后的工作流发布到 `ghcr.io/2991495215/xboard`；升级前必须确认新版本构建成功、
镜像权限和拉取正常，再通过 `XBOARD_IMAGE` 指定该版本。同步源码不会自动升级 s3。

游客接口允许公开浏览已显示的节点状态及知识库；受限知识库内容继续隐藏，
游客不会获得订阅链接。Waffo、Cryptomus 插件源码和 Waffo 桥接程序已入库，
实际令牌、私钥、环境文件及数据不入库。

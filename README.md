## xn_esp32_web_mqtt_manager

ESP32 端的 Web + MQTT 设备管理示例工程，用来演示：

- 设备自动管理 WiFi（失败时开启 AP + Web 配网）
- 设备连接 MQTT 服务器并按约定 Topic 上报状态
- 通过 MQTT 远程下发 WiFi 配置、查询当前状态
- 配合 Web 后台统计设备在线情况

整体包括三部分：

- WiFi 管理与 Web 配网组件：`components/xn_web_wifi_manger`
- MQTT 管理组件：`components/iot_manager_mqtt`
- 配套后台站点：`xn_mqtt_server`（独立部署，有单独说明）

---

## 1. 目录结构

```text
xn_esp32_web_mqtt_manager/
├─ main/                  示例入口 app_main
│  └─ mqtt_app/
│     └─ wifi_config_app.*  通过 MQTT 远程配置 WiFi 的示例
├─ components/
│  ├─ xn_web_wifi_manger/   WiFi 管理 + Web 配网
│  └─ iot_manager_mqtt/     Web MQTT 管理及底层 MQTT 模块
├─ xn_mqtt_server/          PHP + MySQL 后台，详见子目录 README
└─ CMakeLists.txt           ESP-IDF 工程入口
```

日常只需要关心 `main/` 和 `components/` 两个目录即可。

---

## 2. ESP32 端主要功能

### 2.1 WiFi 管理与 Web 配网（xn_web_wifi_manger）

- 自动连接已保存 WiFi，失败会轮询多组配置
- 支持保存多组 WiFi（默认 5 条），掉电不丢失
- 自带配网 AP：
  - 默认 SSID：`XN-ESP32-AP`
  - 默认密码：`12345678`
  - 默认 IP：`192.168.4.1`
  - 默认 Web 配网页面端口：`80`
- 通过回调通知上层：已连接 / 断开 / 本轮全部失败

典型用法：

1. 准备配置结构体 `wifi_manage_config_t`（可用默认宏初始化）
2. 按需修改 AP 名称、密码、端口等字段
3. 调用 `wifi_manage_init(&cfg)` 启动管理任务

### 2.2 MQTT 管理组件（iot_manager_mqtt / web_mqtt_manager）

- 负责维护 MQTT 客户端连接、自动重连和基础心跳
- 管理内部状态机（连接中、已连接、可收发、错误等）
- 通过配置结构体设置：
  - 服务器地址：`broker_uri`（例如 `mqtt://192.168.1.10:1883`）
  - 客户端 ID：`client_id`（为空时自动生成）
  - 账号密码：`username` / `password`
  - Web 管理基础前缀：`base_topic`（例如 `xn/web`）
- 默认上行基础前缀：`xn/esp`
- 通过回调 `web_mqtt_event_cb_t` 告知当前 MQTT 状态

应用只需要：

1. 使用默认宏生成配置
2. 填好 `broker_uri`、`base_topic`、回调等关键字段
3. 调用 `web_mqtt_manager_init(&cfg)`

### 2.3 WiFi 远程配置示例（wifi_config_app）

`main/mqtt_app/wifi_config_app.*` 演示如何通过 MQTT 远程控制 WiFi：

- 订阅 `base_topic = "xn/web"` 下的 WiFi 相关 Topic
- 通过指令下发新的 ssid/password
- 查询当前连接状态、已保存 WiFi 列表
- 切换到指定已保存 WiFi

该示例依赖前面的 WiFi 管理组件和 MQTT 管理组件。

---

## 3. 核心 MQTT Topic 约定（精简）

假设：

- Web 下行基础前缀：`xn/web`
- 设备上行基础前缀：`xn/esp`
- 设备标识：`<device_id>`（实际为 MQTT client_id）

### 3.1 下行（发给设备）

- `xn/web/wifi/<device_id>/set`
  - 下发 WiFi 配置
  - 负载示例：

    ```text
    ssid=你的WiFi
    password=你的密码
    ```

- `xn/web/wifi/<device_id>/get_status`
  - 请求当前 WiFi 状态

- `xn/web/wifi/<device_id>/get_saved`
  - 请求已保存 WiFi 列表

- `xn/web/wifi/<device_id>/connect_saved`
  - 切换到已保存某个 WiFi
  - 负载示例：`ssid=已保存的SSID`

### 3.2 上行（设备上报）

- `xn/esp/wifi/<device_id>/status`
  - 上报当前 WiFi 状态，负载为简单 JSON 字符串

- `xn/esp/wifi/<device_id>/saved`
  - 上报已保存 WiFi 列表，负载为简单 JSON 字符串

其它心跳、注册等 Topic 可以按同样规则扩展。

---

## 4. 示例流程（main/main.c）

当前示例的主流程可以概括为：

1. 初始化并启动 WiFi 管理模块
2. 当回调通知“已连接路由器”时：
   - 根据配置连接 MQTT 服务器
   - 注册 WiFi 远程配置应用模块
3. 之后即可通过 MQTT 下发 WiFi 指令并在上行 Topic 中看到结果

你可以在此基础上继续添加自己的业务模块，例如：

- 远程重启
- 远程修改设备参数
- 上报设备运行状态等

---

## 5. 编译与烧录（ESP-IDF）

1. 本地安装并配置好 ESP-IDF 环境
2. 在工程根目录执行：

```bash
idf.py set-target esp32      # 按实际芯片选择
idf.py menuconfig            # 可选，配置串口等
idf.py build
idf.py flash monitor
```

串口日志中可以看到：

- WiFi 自动连接与重试过程
- MQTT 连接状态变更日志
- 收到 / 处理 WiFi 配置指令的日志

---

## 6. 与 xn_mqtt_server 配合

`xn_mqtt_server/` 目录提供了一个简单的 Web 后台，用来：

- 展示设备在线状态和列表
- 接收设备上行心跳、状态信息
- 支持“管理模式”等辅助功能

一般做法：

- 设备按上面的 Topic 约定上报到 MQTT 服务器
- 在 MQTT 服务器（例如 EMQX）中配置规则，将消息转发到 Web 后台
- Web 后台解析并写入数据库，在页面中展示

具体部署步骤、数据库结构和规则配置示例，请阅读
`xn_mqtt_server/README.md`。

---

## 7. 配置与安全建议

- 修改默认 MQTT 账号密码（可通过宏或配置覆盖）
- 修改默认 AP SSID 和密码，避免被误连
- 调整 `base_topic` 前缀，避免与现有 Topic 冲突
- 若使用 Web 后台，请修改数据库密码、共享密钥，并优先使用 https 访问

根据自己项目的需要，你可以：

- 只使用 WiFi 管理 + Web 配网部分
- 只使用 MQTT 管理和示例应用部分
- 或在现有基础上扩展更多业务模块


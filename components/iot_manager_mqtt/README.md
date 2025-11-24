# iot_manager_mqtt 组件

## 1. 组件功能概述

`iot_manager_mqtt` 组件提供一个 Web MQTT 管理器，用于统一管理设备与 MQTT 服务器之间的连接生命周期：

- 负责建立、维护和重连 MQTT 连接；
- 通过简单的状态枚举向上层报告当前连接状态；
- 通过回调通知上层关键事件（如连接成功、断开、错误等）；
- 对外仅暴露轻量的 `web_mqtt_manager.h` 接口，保持实现细节在 `src/` 内部封装。

该组件适合作为 Web / HTTP 配置页面后的 MQTT 管理后端，与 WiFi 管理组件协同工作。

---

## 2. 对外头文件与宏

对外只需包含：

```c
#include "web_mqtt_manager.h"
```

主要宏：

- **`WEB_MQTT_MANAGER_STEP_INTERVAL_MS`**  
  默认的内部状态机单步运行周期（单位：ms）。

- **`WEB_MQTT_MANAGER_DEFAULT_CONFIG()`**  
  返回一个 `web_mqtt_manager_config_t` 默认配置，可在此基础上仅修改关心的字段。

示例：

```c
web_mqtt_manager_config_t cfg = WEB_MQTT_MANAGER_DEFAULT_CONFIG();
cfg.broker_uri = "mqtt://192.168.1.10:1883";
```

---

## 3. 内部状态机与状态枚举

组件使用简单的内部状态机管理 MQTT 连接，对外暴露的状态定义在 `web_mqtt_state_t` 中：

- **`WEB_MQTT_STATE_DISCONNECTED`**  
  已断开或尚未开始连接。

- **`WEB_MQTT_STATE_CONNECTING`**  
  正在与服务器建立连接。

- **`WEB_MQTT_STATE_CONNECTED`**  
  连接已建立，但仍在做必要订阅或准备工作。

- **`WEB_MQTT_STATE_READY`**  
  已连接且完成必要订阅，可正常收发 MQTT 消息。

- **`WEB_MQTT_STATE_ERROR`**  
  出现错误，等待自动重连或上层干预。

状态机会按 `step_interval_ms`（默认 `WEB_MQTT_MANAGER_STEP_INTERVAL_MS`）周期由内部任务驱动运行。

---

## 4. 事件回调

为了保持低耦合，组件使用回调向上层汇报状态：

- 回调类型：

  ```c
  typedef void (*web_mqtt_event_cb_t)(web_mqtt_state_t state);
  ```

- 当内部状态发生变化时，管理器会调用该回调，参数为当前状态。
- 上层可以在回调中执行：
  - 日志输出；
  - 更新本地 UI 状态；
  - 向云端/本地存储记录状态变更等。

如不关心事件，可将回调设为 `NULL`。

---

## 5. 配置结构体 `web_mqtt_manager_config_t`

初始化时由上层提供配置结构体：

```c
typedef struct {
    const char          *broker_uri;            // MQTT 服务器 URI
    const char          *client_id;             // 客户端 ID
    const char          *username;              // 用户名
    const char          *password;              // 密码
    const char          *base_topic;            // Web 管理相关基础 Topic 前缀
    int                  keepalive_sec;         // MQTT keepalive 时间（秒）
    int                  reconnect_interval_ms; // 自动重连间隔（ms）
    int                  step_interval_ms;      // 状态机运行间隔（ms）
    web_mqtt_event_cb_t  event_cb;              // 事件回调
} web_mqtt_manager_config_t;
```

- **`broker_uri`**：必填，形如 `"mqtt://192.168.1.10:1883"`；
- **`client_id`**：可选，为 `NULL` 时由组件根据芯片唯一 ID 生成；
- **`username/password`**：可选，为 `NULL` 时按服务器匿名配置处理；
- **`base_topic`**：建议填写一个统一前缀，便于在服务器上进行 Topic 管理；
- **`keepalive_sec`**：`<= 0` 时使用组件内置默认值；
- **`reconnect_interval_ms`**：`< 0` 表示关闭自动重连；
- **`step_interval_ms`**：`<= 0` 时使用 `WEB_MQTT_MANAGER_STEP_INTERVAL_MS`；
- **`event_cb`**：事件回调，为 `NULL` 时不进行状态通知。

推荐使用 `WEB_MQTT_MANAGER_DEFAULT_CONFIG()` 初始化，再按需修改字段。

---

## 6. 初始化 API

组件对外只暴露一个初始化入口：

```c
esp_err_t web_mqtt_manager_init(const web_mqtt_manager_config_t *config);
```

- **`config == NULL`** 时：内部使用 `WEB_MQTT_MANAGER_DEFAULT_CONFIG()`；
- 成功返回 `ESP_OK`，失败返回底层 MQTT / 系统相关错误码；
- 调用前应确保网络（如 WiFi）已经连接并可访问 MQTT 服务器。

典型调用顺序：

1. 启动并配置好 WiFi / 以太网组件；
2. 构造 `web_mqtt_manager_config_t`；
3. 调用 `web_mqtt_manager_init(&config)` 启动 MQTT 管理器。

---

## 7. 使用示例

```c
#include "web_mqtt_manager.h"

static void my_mqtt_event_cb(web_mqtt_state_t state)
{
    // 根据状态做日志或上报
}

void app_start_mqtt(void)
{
    web_mqtt_manager_config_t cfg = WEB_MQTT_MANAGER_DEFAULT_CONFIG();

    cfg.broker_uri = "mqtt://192.168.1.10:1883";  // 配置服务器地址
    cfg.base_topic = "xn/web";                    // 业务约定的 Topic 前缀
    cfg.event_cb   = my_mqtt_event_cb;             // 注册事件回调

    // WiFi / 网络需在此之前已经就绪
    esp_err_t ret = web_mqtt_manager_init(&cfg);
    if (ret != ESP_OK) {
        // 处理初始化失败
    }
}
```

---

## 8. 设计说明与扩展

- **低耦合**：
  - 上层只依赖 `web_mqtt_manager.h`，不直接依赖任何 MQTT 客户端实现；
  - 状态与事件采用简单枚举和回调，便于在不同应用中复用。

- **模块化**：
  - 接口定义与实现分离：
    - 头文件位于 `include/web_mqtt_manager.h`；
    - 具体实现位于 `src/` 目录。

- **可扩展**：
  - 如需增加更多事件或统计信息，可在不破坏现有 API 的前提下扩展结构体和回调参数；
  - 业务层可以在 `base_topic` 下自由扩展子 Topic 结构。

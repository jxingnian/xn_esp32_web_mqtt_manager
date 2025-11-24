/*
 * @Author: 星年 jixingnian@gmail.com
 * @Date: 2025-11-22 13:43:50
 * @LastEditors: xingnian jixingnian@gmail.com
 * @LastEditTime: 2025-11-24 13:38:40
 * @FilePath: \xn_web_mqtt_manager\main\main.c
 * @Description: esp32 WEB mqtt管理组件 By.星年
 */

#include <stdio.h>
#include <inttypes.h>
#include "sdkconfig.h"
#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "esp_system.h"
#include "xn_wifi_manage.h"
#include "web_mqtt_manager.h"

static void app_mqtt_event_cb(web_mqtt_state_t state)
{
    printf("MQTT state: %d\n", (int)state);
}

void app_main(void)
{
    printf("esp32 WEB mqtt管理组件 By.星年\n");
    esp_err_t ret = wifi_manage_init(NULL);
    (void)ret;

    web_mqtt_manager_config_t mqtt_cfg = WEB_MQTT_MANAGER_DEFAULT_CONFIG();
    mqtt_cfg.broker_uri = "mqtt://192.168.1.10:1883";
    mqtt_cfg.base_topic = "xn/web";
    mqtt_cfg.event_cb   = app_mqtt_event_cb;

    esp_err_t ret_mqtt = web_mqtt_manager_init(&mqtt_cfg);
    (void)ret_mqtt;
}

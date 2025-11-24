/*
 * @Author: 星年 jixingnian@gmail.com
 * @Date: 2025-11-22 13:43:50
 * @LastEditors: xingnian jixingnian@gmail.com
 * @LastEditTime: 2025-11-24 15:09:13
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

static int s_mqtt_started = 0;

static void app_wifi_event_cb(wifi_manage_state_t state)
{
    if (state == WIFI_MANAGE_STATE_CONNECTED && !s_mqtt_started) {
        web_mqtt_manager_config_t mqtt_cfg = WEB_MQTT_MANAGER_DEFAULT_CONFIG();
        mqtt_cfg.broker_uri = "mqtt://120.55.96.194:1883";
        mqtt_cfg.base_topic = "xn/web";
        mqtt_cfg.event_cb   = app_mqtt_event_cb;

        esp_err_t ret_mqtt = web_mqtt_manager_init(&mqtt_cfg);
        (void)ret_mqtt;

        s_mqtt_started = 1;
    }
}

void app_main(void)
{
    printf("esp32 WEB mqtt管理组件 By.星年\n");

    wifi_manage_config_t wifi_cfg = WIFI_MANAGE_DEFAULT_CONFIG();
    wifi_cfg.wifi_event_cb = app_wifi_event_cb;

    esp_err_t ret = wifi_manage_init(&wifi_cfg);
    (void)ret;
}

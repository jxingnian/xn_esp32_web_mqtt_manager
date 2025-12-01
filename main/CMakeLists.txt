idf_component_register(SRCS "main.c" "mqtt_app/wifi_config_app.c"
                       PRIV_REQUIRES xn_web_wifi_manger xn_iot_manager_mqtt
                       INCLUDE_DIRS "." "mqtt_app")

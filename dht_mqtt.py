#!/usr/bin/env python3
import time
import json
import board
import adafruit_dht
import paho.mqtt.client as mqtt

DHT_PIN = board.D4

MQTT_BROKER = "localhost"
MQTT_PORT = 1883
MQTT_USERNAME = "mqttZ2M"
MQTT_PASSWORD = "your_password"
MQTT_TOPIC = "home/dht22"

dht = adafruit_dht.DHT22(DHT_PIN)

def read_sensor():
    try:
        temperature = dht.temperature
        humidity = dht.humidity
        return humidity, temperature
    except RuntimeError as e:
        return None, None

def on_connect(client, userdata, flags, rc, properties=None):
    if rc == 0:
        print(f"Connected to MQTT broker")
    else:
        print(f"Failed to connect, return code {rc}")

def main():
    client = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
    client.username_pw_set(MQTT_USERNAME, MQTT_PASSWORD)
    client.on_connect = on_connect

    client.connect(MQTT_BROKER, MQTT_PORT, 60)
    client.loop_start()

    try:
        while True:
            humidity, temperature = read_sensor()
            if humidity is not None and temperature is not None:
                payload = json.dumps({
                    "temperature": round(temperature, 1),
                    "humidity": round(humidity, 1)
                })
                client.publish(MQTT_TOPIC, payload)
                print(f"Published: {payload}")
            time.sleep(30)
    except KeyboardInterrupt:
        client.loop_stop()
        client.disconnect()

if __name__ == "__main__":
    main()

import serial
import paho.mqtt.client as mqtt
import json
from smllib import SmlStreamReader

# --- CONFIGURATION ---
MQTT_BROKER = "localhost"
MQTT_PORT = 1883
MQTT_USERNAME = "mqttZ2M"
MQTT_PASSWORD = "TODO"
MQTT_TOPIC = "sensors/emh"
SERIAL_PORT = "/dev/ttyAMA0"
BAUD_RATE = 9600
TIMEOUT = 5

# Mapping OBIS to your desired MQTT keys
OBIS_MAPPING = {
    "1-0:1.8.0*255": "bezug_kwh",
    "1-0:2.8.0*255": "einspeisung_kwh",
    "1-0:16.7.0*255": "leistung_w",
    "1-0:1.8.1*255": "bezug_t1_kwh",
}

# --- MQTT SETUP ---
try:
    client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION2)
    client.username_pw_set(MQTT_USERNAME, MQTT_PASSWORD)

    client.connect(MQTT_BROKER, MQTT_PORT, 60)
    client.loop_start()
    print(f"Connected to MQTT broker")
except Exception as e:
    print(f"MQTT Connect Error: {e}")

# --- SERIAL SETUP ---
print(f"Opening serial port {SERIAL_PORT} at {BAUD_RATE} baud...")
ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=TIMEOUT)
reader = SmlStreamReader()
print(f"Serial port opened, SML reader ready")

print(f"Reading and publishing to {MQTT_TOPIC}...")

try:
    while True:
        data = ser.read(ser.in_waiting or 1)
        if data:
            reader.add(data)

            frame = reader.get_frame()

            if frame:
                power_payload = {}
                energy_payload = {}

                try:
                    parsed_msgs = frame.parse_frame()
                    for msg in parsed_msgs:
                        if hasattr(msg, 'message_body'):
                            mb = msg.message_body
                            if hasattr(mb, 'val_list') and mb.val_list:
                                for entry in mb.val_list:
                                    if hasattr(entry, 'obis'):
                                        obis_obj = entry.obis
                                        obis_code = None
                                        if hasattr(obis_obj, 'obis_code'):
                                            obis_code = obis_obj.obis_code
                                        elif isinstance(obis_obj, str):
                                            obis_code = obis_obj
                                        elif isinstance(obis_obj, bytes):
                                            obis_code = obis_obj.hex()
                                        if obis_code and obis_code in OBIS_MAPPING:
                                            scaler = entry.scaler if entry.scaler else 0
                                            value = entry.value * (10 ** scaler)
                                            mqtt_key = OBIS_MAPPING[obis_code]
                                            if mqtt_key == "leistung_w":
                                                power_payload[mqtt_key] = value
                                            else:
                                                energy_payload[mqtt_key] = value
                    if power_payload:
                        client.publish(MQTT_TOPIC + "/power", json.dumps(power_payload))
                    if energy_payload:
                        client.publish(MQTT_TOPIC + "/energy", json.dumps(energy_payload))
                except Exception:
                    pass
        import time
        time.sleep(5)

except KeyboardInterrupt:
    print("Stopping...")
    client.loop_stop()
    ser.close()
except Exception as e:
    import traceback
    print(f"Loop Error: {e}")
    traceback.print_exc()

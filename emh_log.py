import serial
import logging
import sys
from smllib import SmlStreamReader

# 1. Setup Logging to see what's happening internally
logging.basicConfig(
    level=logging.DEBUG,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)]
)
logger = logging.getLogger("SML_Debug")

# 2. Configuration
SERIAL_PORT = '/dev/ttyAMA0'  # or /dev/serial0
BAUD_RATE = 9600              # Standard for SML
TIMEOUT = 5                   # Seconds to wait for data

try:
    ser = serial.Serial(SERIAL_PORT, BAUD_RATE, timeout=TIMEOUT)
    logger.info(f"Connected to {SERIAL_PORT} at {BAUD_RATE} baud.")
except Exception as e:
    logger.error(f"Failed to open serial port: {e}")
    sys.exit(1)

reader = SmlStreamReader()

logger.info("Starting read loop. Waiting for SML frames...")

while True:
    try:
        # Read a small chunk of data
        data = ser.read(64)

        if not data:
            logger.warning("No data received within timeout. Is the IR head aligned?")
            continue

        # Log raw hex for low-level debugging
        logger.debug(f"Raw Data (Hex): {data.hex()}")

        # Add to SML parser
        reader.add(data)

        # Try to extract a full frame
        frame = reader.get_frame()
        if frame:
            logger.info("--- Full SML Frame Received ---")
            for list_entry in frame.list:
                # Log OBIS code and value
                logger.info(f"OBIS: {list_entry.obj_id} | Value: {list_entry.value} {list_entry.unit if list_entry.unit else ''}")

    except KeyboardInterrupt:
        logger.info("Stopping debug script.")
        ser.close()
        break
    except Exception as e:
        logger.error(f"Error during processing: {e}")


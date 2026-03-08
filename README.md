# schlauhaus

## System based on Raspberry Pi

Lite Version (only SSH)

### System update and Docker + Portainer

    # upgrade all existing packages
    sudo apt update
    sudo apt upgrade -y

    sudo raspi-config # Advanced Options -> Expand Filesystem

    # Install Docker
    curl -sSL https://get.docker.com | sh

    # add our current user to the docker group
    sudo usermod -aG docker $USER
    newgrp docker

    # Install Docker Compose (optional)
    sudo apt install -y docker-compose

    # Installing Portainer to the Raspberry Pi
    docker pull portainer/portainer-ce:latest
    # Docker volume for Portainer
    docker volume create portainer_data
    # launch Portainer
    docker run -d -p 8000:8000 -p 9443:9443 --name portainer --restart=always -v /var/run/docker.sock:/var/run/docker.sock -v portainer_data:/data portainer/portainer-ce:latest
    # Get IP Address
    hostname -I


--------------------------------------------------------
PORTAINER KONFIGURATION:
--------------------------------------------------------
Name:
IP-Symcon

Image:
symcon/symcon:latest

Ports:

    3777 - 3777 TCP
    1883 - 1883 TCP - Für MQTT-Server (wenn aus IP-Symcon und nicht aus Mosquitto etc.)

    docker volume create symcon_data
    docker volume create symcon_log
    docker volume create symcon_root

## eCPC

docker exec -it mosquitto mosquitto_passwd /mosquitto/data/passwd mqttZ2M

mosquitto_pub -h localhost -p 1883 -t /topic -m "Authenticated message" -u username -P password

ExecStart=/home/rasp_local/dht_env/bin/python3
/home/rasp_local/dht_env/dht_mqtt.py                                            

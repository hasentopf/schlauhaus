# schlauhaus

## System based on Raspberry Pi

Lite Version (only SSH)

### System update and Docker + Portainer

    # upgrade all existing packages
    sudo apt update
    sudo apt upgrade -y

    sudo raspi-config 
    # Advanced Options → Expand Filesystem
    # Interface Options → Serial Port                                                                                                                                                                                                                                                                             
    # Would you like a login shell to be accessible over serial? → Select No                                                                                                                                                                                                                                      
    # Would you like the serial port hardware to be enabled? → Select Yes
    sudo reboot        

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
    # Go to https://<IP-Adresse>:9443 and create a admin user for portainer

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

Network: host

Volumes:
    Container: /var/lib/symcon  Host: /home/rasp_user/symcon/data  Bind
    Container: /var/log/symcon Host: /home/rasp_user/symcon/log  Bind
    Container: /root                  Host: /home/rasp_user/symcon/license      Bind

Env:
TZ Europe/Berlin

Restart policy:
always


## Backup
    mkdir ~/bin
    cd ~/bin
    vi backup.sh
    chmod +x backup.sh
    ./backup.sh
    mkdir ~/backups

### USB-Stick auf Raspberry PI mounten:
    sudo fsck.vfat -a /dev/sda1
    # mount with clean permissions:
    sudo mount -o uid=1000,gid=1000,umask=000 /dev/sda1 /mnt/usb
    # make it permanent: 
    sudo nano /etc/fstab
    # add this line:
    /dev/sda1  /mnt/usb  vfat  user,rw,umask=000,uid=1000,gid=1000,nofail  0  0
    # run in cronjob
    crontab -e
    # run it every night at 3:00 AM
    0 3 * * * /home/rasp_user/backup_z2m.sh 


## eCPC

ExecStart=/home/rasp_local/dht_env/bin/python3
/home/rasp_local/dht_env/dht_mqtt.py                                            

    pip install paho-mqtt --break-system-packages
    pip3 install adafruit-circuitpython-dht --break-system-packages
    python3 dht_mqtt.py    

    sudo nano /etc/systemd/system/dht_mqtt.service
    [Unit]
    Description=DHT22 MQTT Publisher
    After=network.target
    
    [Service]
    User=rasp_user
    WorkingDirectory=/home/rasp_user/dht_mqtt
    ExecStart=/usr/bin/python3 /home/rasp_user/dht_mqtt/dht_mqtt.py
    Restart=always
    
    [Install]
    WantedBy=multi-user.target

    sudo systemctl enable dht_mqtt.service
    sudo systemctl start dht_mqtt.service

    [Unit]
    Description=EMH Electricity Meter MQTT
    Publisher
    After=network.target
    
    [Service]
    User=rasp_user
    WorkingDirectory=/home/rasp_user/emh_mqtt
    ExecStart=/usr/bin/python3 /home/rasp_user/emh_mqtt/emh_mqtt.py
    Restart=always
    
    [Install]
    WantedBy=multi-user.target


## Unifi Protect Cam Stream
rtsps://192.168.0.1:7441/zIqTfSvGztc01ykK?enableSrtp
rtsp://192.168.0.1:7447/zIqTfSvGztc01ykK

streams:
  keller_cam:
    - rtspx://192.168.0.1:7441/zIqTfSvGztc01ykK

docker run -d --name go2rtc --network host --privileged --restart unless-stopped -e TZ=Europe/Berlin -v ~/go2rtc:/config alexxit/go2rtc

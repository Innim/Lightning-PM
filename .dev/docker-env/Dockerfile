FROM debian:9
USER root

ARG sshpass=secret
ARG php_version=7.3

WORKDIR /root
ENV DEBIAN_FRONTEND noninteractive

RUN apt -y update && \
	apt -y upgrade

RUN apt -y install  wget mc vim openssh-server supervisor apt-transport-https lsb-release ca-certificates

RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

RUN apt update && apt -y install git apache2 libapache2-mod-php$php_version libapache2-mod-fcgid libapache2-mod-rpaf php$php_version php$php_version-curl php$php_version-gd php$php_version-intl php$php_version-ldap php$php_version-mcrypt php$php_version-memcached php$php_version-mysql php$php_version-pgsql php$php_version-pspel php$php_version-xsl php$php_version-mbstring php$php_version-xml php$php_version-zip

RUN a2enmod actions authz_groupfile cgi headers rewrite socache_shmcb ssl

ADD config/etc/apache2/apache2.conf /etc/apache2/apache2.conf
ADD config/etc/apache2/sites-available /etc/apache2/sites-available
ADD config/etc/apache2/sites-enabled /etc/apache2/sites-enabled

ADD config/etc/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

ADD config/etc/php/$php_version/apache2/php.ini /etc/php/$php_version/apache2/php.ini


#RUN mkdir /var/run/sshd
#RUN echo 'root:secret' | chpasswd
#RUN sed -i 's/#PermitRootLogin prohibit-password/PermitRootLogin yes/' /etc/ssh/sshd_config
#RUN sed -i 's/PermitRootLogin without-password/PermitRootLogin yes/' /etc/ssh/sshd_config

CMD ["/usr/bin/supervisord"]

EXPOSE 80 443 22


FROM centos
MAINTAINER Yusuke Tsujisawa <yusuke@tsujisawa.com>

RUN echo "now building..."

RUN yum -y update

RUN yum -y install httpd

# EPELリポジトリ
RUN yum -y install epel-release

# Remiリポジトリ
RUN rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-7.rpm

# php7.0
RUN yum -y install --enablerepo=remi,remi-php70 php php-devel php-mbstring php-pdo php-gd php-mysql php-psgsql

RUN yum -y install wget unzip

COPY build/httpd.conf /etc/httpd/conf
COPY build/php.ini /etc

RUN mkdir -p /var/www
RUN mkdir -p /var/public
RUN mkdir -p /var/application
RUN mkdir -p /var/public/uploads

# Add files from WWW folder
COPY public /var/www/public
COPY application /var/www/application

CMD ["chmod","775","/var/www/public/uploads"]

CMD ["cd","/usr/share/php"]
CMD ["wget","https://packages.zendframework.com/releases/ZendFramework-1.12.20/ZendFramework-1.12.20.zip"]
CMD ["unzip","ZendFramework-1.12.20.zip"]
CMD ["rm","-rf","ZendFramework-1.12.20.zip"]
CMD ["mv","ZendFramework-1.12.20/library/Zend","Zend"]
CMD ["rm ","-rf","/usr/share/php/ZendFramework-1.12.20"]

# Do some web maping
VOLUME ["/var/www"]

EXPOSE 80

CMD ["/usr/sbin/httpd","-D","FOREGROUND"]


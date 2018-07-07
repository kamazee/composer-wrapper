FROM debian:stretch-slim

RUN apt-get --assume-yes update

# Dependencies to download and unpack source (pigz and pbzip2 are parallel gz and bzip2)
RUN DEBIAN_FRONTEND=noninteractive apt-get --quiet --assume-yes install apt-utils curl pigz pbzip2 pkg-config

# Dependencies to build bare PHP
RUN DEBIAN_FRONTEND=noninteractive apt-get --quiet --assume-yes install build-essential autoconf automake libtool

# Dependencies for the specific configuration of PHP
RUN DEBIAN_FRONTEND=noninteractive apt-get --quiet --assume-yes install libxml2 libxml2-dev libssl-dev

WORKDIR /root
RUN curl --output openssl-1.0.2o.tar.gz https://www.openssl.org/source/openssl-1.0.2o.tar.gz
RUN echo "ec3f5c9714ba0fd45cb4e087301eb1336c317e0d20b575a125050470e8089e4d  openssl-1.0.2o.tar.gz" | sha256sum --check -
RUN tar --use-compress-program=pigz --extract --file=openssl-1.0.2o.tar.gz
WORKDIR /root/openssl-1.0.2o
# make install_sw only installs software, we don't need man there
RUN ./config --prefix=/opt/openssl-1.0.2o && make -j$(nproc) && make install_sw

WORKDIR /root
RUN curl --output php-5.3.3.tar.bz2 http://museum.php.net/php5/php-5.3.3.tar.bz2
RUN echo "f2876750f3c54854a20e26a03ca229f2fbf89b8ee6176b9c0586cb9b2f0b3f9a  php-5.3.3.tar.bz2" | sha256sum --check -
RUN tar --use-compress-program=pbzip2 --extract --file php-5.3.3.tar.bz2
RUN curl --output php53-libxml.patch https://mail.gnome.org/archives/xml/2012-August/txtbgxGXAvz4N.txt
RUN echo "c834246a33f7518bb76e292a658da955ca4a4103d2eb144e18124721f3d2b10b  php53-libxml.patch" | sha256sum --check -
WORKDIR /root/php-5.3.3
# Workaround for building PHP 5.3 with modern libxml, taken from
# https://stackoverflow.com/questions/28211039/phpbrew-5-3-10-build-error-dereferencing-pointer-to-incomplete-type
RUN patch -p0 < /root/php53-libxml.patch
RUN ./configure --prefix=/opt/php-5.3.3 \
    --disable-cgi \
    --without-sqlite3 \
    --without-pdo_sqlite \
    --with-openssl=/opt/openssl-1.0.2o \
    && make -j$(nproc) \
    && make install
RUN /opt/php-5.3.3/bin/pecl install xdebug-2.2.7
RUN ln --symbolic /opt/php-5.3.3/bin/php /usr/local/bin/php533

WORKDIR /root
RUN curl --location --output php-7.2.7.tar.bz2 http://php.net/distributions/php-7.2.7.tar.bz2
RUN echo "cc81675a96af4dd18d8ffc02f26a36c622abadf86af7ecfea7bcde8d3c96d5a3  php-7.2.7.tar.bz2" | sha256sum --check -
RUN tar --use-compress-program=pbzip2 --extract --file=php-7.2.7.tar.bz2
WORKDIR /root/php-7.2.7
RUN ./configure --prefix=/opt/php-7.2.7 \
    --disable-cgi \
    --without-sqlite3 \
    --without-pdo_sqlite \
    --with-openssl \
    --enable-mbstring \
    && make -j$(nproc) \
    && make install

RUN /opt/php-7.2.7/bin/pecl install xdebug

RUN ln --symbolic /opt/php-7.2.7/bin/php /usr/local/bin/php727

# To make composer work with --prefer-dist work (requires unzip program or zip ext)
RUN DEBIAN_FRONTEND=noninteractive apt-get install --quiet --assume-yes unzip

RUN useradd --uid 1000 --create-home --home-dir /opt/project project
USER project
WORKDIR /opt/project
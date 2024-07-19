#!/bin/sh

# I love web browsers!
# Please see the below Stack Overflow post for more information:
# https://stackoverflow.com/a/27931596
# also this:
# https://github.com/bencmbrook/certificate-authority

# Writing this was NOT fun.

# fix windows:
MSYS_NO_PATHCONV=1

# # Create a certificate authority:
# openssl req -config config.conf -new -x509 -sha256 -newkey rsa:2048 -nodes \
#     -keyout server.key -days 3650 -out server.crt
    
# # Create a signing request for the CA:
# openssl req -config config.conf -new -sha256 -newkey rsa:2048 -nodes \
#     -keyout server.key -out server.csr

# Create a certificate authority:
openssl req -x509 -config ca.conf -newkey rsa:4096 -sha256 -nodes -days 3650 \
    -out ca-crt.pem -outform PEM
    
# Create a certificate for twitter.com:
openssl req -config config.conf -newkey rsa:2048 -sha256 -nodes \
    -out server.csr -outform PEM
    
openssl ca -batch -config ca.conf -policy signing_policy \
    -extensions signing_req -out server.crt \
    -infiles server.csr
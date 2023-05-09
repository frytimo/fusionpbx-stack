# fusionpbx-stack
<p>clone with</p>

```
git clone --recursive https://github.com/frytimo/fusionpbx-stack.git
```

<p>then create a .env file or copy the example</p>

```
cp fusionpbx-stack/.env.example fusionpbx-stack/.env
```

<p>then create a config.conf file or copy the example</p>

```
cp fusionpbx-stack/fusionpbx/etc/config.conf.example fusionpbx-stack/fusionpbx/etc/config.conf
```


<p>Clone the repos for freeswitch, spandsp, sofia-sip using:</p>

```
cd fusionpbx-stack/freeswitch/build && git clone https://github.com/signalwire/freeswitch && git clone https://github.com/freeswitch/spandsp && git clone https://github.com/freeswitch/sofia-sip && cd ../..
```

<p>Now execute the stack</p>

```
docker-compose up
```



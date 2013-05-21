# ALD
[![Build Status](https://travis-ci.org/Library-Distribution/ALD-API.png "travis-ci.org build status")](http://travis-ci.org/Library-Distribution/ALD-API)
[![Build Status "master"](https://travis-ci.org/Library-Distribution/ALD-API.png?branch=master "travis-ci.org build status for branch "master"")](http://travis-ci.org/Library-Distribution/ALD-API)

***ALD*** is short for ***A***utoHotkey ***L***ibrary ***D***istribution. It is a system for management, distribution and installation of libraries and applications written in AutoHotkey.
This system consists of several parts. The most important part is the HTTP server API. Also, there can be a lot of different clients: websites, desktop apps and more.
More information on the ALD system can be read up [in the docs](http://about.libba.net/docs/api/current/ALD-model.html).

## This repo
The code in this repo is part of the ALD system. It's the HTTP API that is running on an ALD server to handle the libraries and applications stored on that server.

### Get started
To use the API, you only need to be able to issue standard HTTP requests. Read up the documentation on the API methods in the [docs](http://about.libba.net/docs/api/current).

Keep in mind that this is still in development, and breaking changes can occur at any time. At the moment, version `0.2.0` is live on `api.libba.net`. To test the current version from your client,
just issue a `GET` request to `http://api.libba.net/version` (with the appropriate `Accept` header).

Also note that the documentation is slightly out of date and incomplete at the moment.

### Development
You can watch active development in this repo. For example check the open [issues](https://github.com/Library-Distribution/ALD-API/issues) and [pull requests](https://github.com/Library-Distribution/ALD-API/issues), and comment on them if you have something to say.
If you wish further information, or want to get involved in development, be my guest. Just contact me, and if you want to contribute, fork the repo on github. I can then give you more information on planned features and tasks to complete.

## ALD Clients
There are several clients for this backend in development. Most importantly, there's [libba.net](http://libba.net), the official website, which presents the data stored in the backend in a user-friendly way.
# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

<!-- ### [Unreleased] -->


### 1.1.2

#### Added
- Added the `setDieOnFatal` function to give users the ability log a fatal error without the runtime calling `die()`
- Throws exception unless run on 64 bit PHP platform

### 1.1.1

#### Changed
- Logs `error_flag` as string values `'true'` or `'false'`


### 1.1.0

#### Changed
- Connects to collector over secure port by default 

# Upgrading to version %%VERSION%%

## Overview

[//]: # (Briefly describe what makes this upgrade different from a routine update)

[//]: # (and why manual intervention is required.)

In this version we introduced a better solution to propagate the "reverb" (websocket) configuration to the frontend. Therefore the values must no longer be available at build time. For you, there is no need to intervene, but you probably like your `.env` file to be clean and tidy, so you can remove the `VITE_REVERB_*` variables from there.

## Steps

### 1. Example step

[//]: # (Describe what the user needs to do.)

## Notes

[//]: # (Any additional warnings or tips for administrators performing the upgrade.)

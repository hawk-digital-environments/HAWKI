# Temporary implementation

Because GWDG needs a /chat/completions endpoint, we can not use the built-in OpenAI driver, which uses the /responses format.

In [this PR](https://github.com/laravel/ai/pull/742) a solution was proposed to add a new driver for OpenAI that uses the /chat/completions endpoint. However, this PR has not been merged yet. As a temporary measure, we have implemented we
copied the driver files until the PR is merged and we can use the official driver.

All files in this directory are copied (namespaces adjusted) from the PR mentioned above.

**EXPECT changes/the removal of this directory once the PR is merged and we can use the official driver.**

@todo keep an eye on the PR and remove this directory once it is merged and the official driver can be used.

> In order to get the code to work, I had to pull some changes from the current `development` branch
> https://github.com/laravel/ai/commit/4f939c5ed6c746ebfc71de066f29f99daf960a85#diff-b21c33bebc634727c7c4b8ef6829c3ed174a39275acd61809e561bd57a08ac2a
> The required code was also cloned and adapted in the `Foreign` namespace.

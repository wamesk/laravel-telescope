# Laravel Telescope Improve

## Add path fultext search

**TELESCOPE_SEARCH**

## Add tags to request

**TELESCOPE_TAG_URL**
- `api:GET /api/v1/users`
- `method:GET`
- `status:200`
- `url:/api/v1/users`

**TELESCOPE_TAG_USER**
- `user_id:guest`
- `user_email:guest@example.com`

**TELESCOPE_TAG_DATE**
- `date:2024-01-01`
- `date_time:2024-01-01 11:22:33`
- `time:11:22`

**TELESCOPE_TAG_CODE**
- `response_code:1.2.3`

## Instalation

```shell
composer require wamesk/laravel-telescope
```

- No setup is required.
- Middleware is added via ServiceProvider in the package.
- If it is necessary to disable a group of tags, it is possible in `.env`, e.g. `TELESCOPE_TAG_CODE=false` 

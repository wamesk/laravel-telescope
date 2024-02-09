# Laravel Telescope Improve

## Instalation

```shell
composer require wamesk/laravel-telescope
```

- No setup is required.
- Middleware is added via ServiceProvider in the package.


## Search
Adds fulltext search in path, content.
Add tags to all requests.

| Key      | .env                    | Default | Search without tag | Example                                                     |
|----------|-------------------------|---------|:------------------:|-------------------------------------------------------------|
| /        |                         |         |         ×          | `/api/v1`                                                   |
| Api      | TELESCOPE_TAG_API       | false   |         ×          | `Api:GET /api/v1/users`                                     |
| Code     | TELESCOPE_TAG_CODE      | true    |         ✓          | `Code:1.2.3`                                                |
| Date     | TELESCOPE_TAG_DATE      | false   |         ✓          | `Date:2023-01-01`                                           |
| DateTime | TELESCOPE_TAG_DATE_TIME | false   |         ✓          | `DateTime:2023-01-01 11:22:33`                              |
| Email    | TELESCOPE_TAG_EMAIL     | true    |         ×          | `Email:john.doe@example.com` or `Email:none`                |
| Errors   | TELESCOPE_TAG_ERROR     | true    |         ×          | `Errors:true`                                               |
| From     |                         |         |         ✓          | `From:2024-01-01`, `From:12:34`, `From:2024-01-01 12:34`... |
| Hour     | TELESCOPE_TAG_HOUR      | false   |         ✓          | `Hour:0` - `Hour:23`                                        |
| Method   | TELESCOPE_TAG_METHOD    | false   |         ✓          | `Method:GET`, `Method:POST`, `Method:UPDATE`...             |
| Month    | TELESCOPE_TAG_MONTH     | false   |         ✓          | `Month:1` - `Month:12`                                      |
| Path     | TELESCOPE_TAG_PATH      | true    |         ✓          | `Path:/api/v1`                                              |
| Status   | TELESCOPE_TAG_STATUS    | false   |         ✓          | `Status:200`, `Status:400`, `Status:500`...                 |
| Time     | TELESCOPE_TAG_TIME      | false   |         ✓          | `Time:11:22`                                                |
| To       |                         |         |         ✓          | `To:2024-01-01`, `To:12:34`, `To:2024-01-01 12:34`...       |
| Url      | TELESCOPE_TAG_URL       | false   |         ✓          | `Url:/nova-api/user-groups?page=1&perPage=25`               |

- To disable fulltext search, set `TELESCOPE_SEARCH=false` in `.env`.
- You can set the tag saving settings by adding a parameter to the `.env` e.g. `TELESCOPE_TAG_CODE=false` 
- It is possible to search according to these keys even if saving tags is not allowed.
- By turning off the tag, you will only lose a quick filter.
- Keys can be written in case-insensitive

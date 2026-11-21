# Deployment

- This project runs locally only, via `docker compose up --build` from the repo root (web, api, Postgres, Mailpit). No paid services or hosting accounts.
- Do not suggest or configure Laravel Cloud, Forge, Vapor or other hosted platforms.
- The intended production path (documented, not built) is AWS: ECS Fargate (php-fpm/nginx or Octane), RDS PostgreSQL, SES via `MAIL_MAILER=ses`, SQS queues, scheduled `catalogue:import`. See TECH_STACK.md §9 in the repo root.

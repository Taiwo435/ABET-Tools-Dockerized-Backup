# Contributing

Since we have so many people here, I would like to have rules for contributing. We can't all develop on main, so here's the deal:

When working on a feature:

1) create a new branch and work on that feature. (name it `feature/<feature-name>`. Don't worry if you already named it)
2) Once you're done, create a pull request to merge it into the main branch.
3) Someone will review the PR and merge it (most likely me).

I (Danny, in charge of integration), will resolve merge conflicts. If it's bad enough, I might contact you.

## Special Contribution Notes

**Database Schema Changes**: Any change on the database that you want reflected in prod should have [Database Migrations](/docs/mysql/database.md).

**Optional, but Recommended:** Use [Conventional Commit messages](https://gist.github.com/qoomon/5dfcdf8eec66a051ecd85625518cfd13). 
They don't have to fit the spec perfectly, but this format is super descriptive and gets you in the habit of micro-committing. 

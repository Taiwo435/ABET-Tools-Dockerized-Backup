# Contributing

Since we have so many people here, I would like to have rules for contributing. We can't all develop on main, so here's the deal:

## Finding issues to work on

Find tasks to work on [here at Github Issues](https://github.com/hoang-danny05/ABET-Tools-Dockerized/issues). 
If you want to work on a task that isn't here, please add a new one. 

## Choosing something to work on

**ASSIGN** yourself to an issue to an issue to avoid work duplication! 
You can assign yourself to multiple issues. 

<img width="1286" height="380" alt="image" src="https://github.com/user-attachments/assets/b561f1a8-5c8c-4e6d-a9c6-e86901dd345c" />

> [!NOTE]
> If you want to work with someone else on the same issue, assign both people, and comment how you're going to split the work.
> If you don't do this, work will almost certainly be duplicated.


## When working on a feature:

1) create a new branch and work on that feature. (name it `feature/<feature-name>`. Don't worry if you already named it)
2) Once you're done, create a pull request to merge it into the main branch.
3) Someone will review the PR and merge it (most likely me).

I (Danny, in charge of integration), will resolve merge conflicts. If it's bad enough, I might contact you.

## Special Contribution Notes

**Database Schema Changes**: Any change on the database that you want reflected in prod should have [Database Migrations](/docs/mysql/database.md).

**Optional, but Recommended:** Use [Conventional Commit messages](https://gist.github.com/qoomon/5dfcdf8eec66a051ecd85625518cfd13). 
They don't have to fit the spec perfectly, but this format is super descriptive and gets you in the habit of micro-committing. 

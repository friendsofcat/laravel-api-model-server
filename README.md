# laravel-api-model-server
Easy to implement configurable API solution for Eloquent Models.

Goes hand in hand with [Laravel API model package.](https://github.com/matta-davi/laravel-api-model)
<br></br>
## Features:

* Configurable schema - used as a transformation layer between model and API
  * allow / restrict specific attributes
  * allow / restrict specific scopes
  * allow eager load specific relations with possibility to allow only specific columns
  * ability to set attribute / scope aliases
  * allow specific methods like ‘get’, ‘exists’, ‘count’, ‘avg’ etc. (defaults to ‘get’, ‘exists’)
  * allow raw clauses (restricted by default)

* Validates incoming payload according to schema

* Prepares data for query builder

* Builds query

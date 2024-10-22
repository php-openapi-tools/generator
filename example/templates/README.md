# [READONLY-SUBSPLIT] {{ package.name }}

![Continuous Integration](https://github.com/php-api-clients/{{ package.name }}/workflows/Continuous%20Integration/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/api-clients/{{ package.name }}/v/stable.png)](https://packagist.org/packages/api-clients/{{ package.name }})
[![Total Downloads](https://poser.pugx.org/api-clients/{{ package.name }}/downloads.png)](https://packagist.org/packages/api-clients/{{ package.name }})
[![License](https://poser.pugx.org/api-clients/{{ package.name }}/license.png)](https://packagist.org/packages/api-clients/{{ package.name }})

Non-Blocking first {{ package.metadata.name }} client, this is a read only sub split, see [`github-root`](https://github.com/php-api-clients/github-root).

{{ package.metadata.description }}

## Supported operations

{% for path in representation.client.paths %}
{% for operation in path.operations %}

### {{ operation.operationId }}

{{ operation.summary }}

Using the `call` method:
```php
$client->call('{{ operation.matchMethod }} {{ operation.path }}'{% if operation.parameters|length > 0 %}, [
{% for parameter in operation.parameters %}        '{{ parameter.targetName }}' => {% if parameter.type == 'string' %}'{% endif %}{{ parameter.example.raw }}{% if parameter.type == 'string' %}'{% endif %},
{% endfor %}]{% endif %});
```

Operations method:
```php
$client->operations()->{{ operation.groupCamel }}()->{{ operation.nameCamel }}(
{% if operation.parameters|length > 0 %}{% for parameter in operation.parameters %}        {{ parameter.targetName }}: {% if parameter.type == 'string' %}'{% endif %}{{ parameter.example.raw }}{% if parameter.type == 'string' %}'{% endif %},
{% endfor %}{% endif %});
```

{% if operation.externalDocs != null %}
You can find more about this operation over at the [{{ operation.externalDocs.description }}]({{ operation.externalDocs.url }}).
{% endif %}

{% endfor %}
{% endfor %}

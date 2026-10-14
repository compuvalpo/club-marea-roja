<?php

declare(strict_types=1);

/*
| Mensajes de validación en español (Chile). Los errores se devuelven por campo,
| por lo que los mensajes no repiten el nombre del atributo.
*/

return [
    'accepted' => 'Debe ser aceptado.',
    'after' => 'Debe ser una fecha posterior a :date.',
    'after_or_equal' => 'Debe ser una fecha igual o posterior a :date.',
    'array' => 'Debe ser una lista.',
    'before' => 'Debe ser una fecha anterior a :date.',
    'boolean' => 'Debe ser verdadero o falso.',
    'date' => 'Debe ser una fecha válida.',
    'date_format' => 'Debe tener el formato :format.',
    'email' => 'Debe ser un correo válido.',
    'exists' => 'El valor seleccionado no existe.',
    'in' => 'Valor no permitido.',
    'integer' => 'Debe ser un número entero.',
    'max' => [
        'array' => 'Debe tener como máximo :max elementos.',
        'numeric' => 'Debe ser como máximo :max.',
        'string' => 'Debe tener como máximo :max caracteres.',
    ],
    'min' => [
        'array' => 'Debe tener al menos :min elementos.',
        'numeric' => 'Debe ser al menos :min.',
        'string' => 'Debe tener al menos :min caracteres.',
    ],
    'numeric' => 'Debe ser numérico.',
    'regex' => 'El formato no es válido.',
    'required' => 'El campo es obligatorio.',
    'required_without' => 'El campo es obligatorio si no se indica :values.',
    'string' => 'Debe ser texto.',
    'unique' => 'El valor ya está registrado.',
    'custom' => [
        'email' => ['unique' => 'El correo ya está registrado.'],
        'end_time' => ['after' => 'La hora de término debe ser posterior a la de inicio.'],
        'end_at' => ['after' => 'El término debe ser posterior al inicio.'],
        'period' => ['regex' => 'Debe tener formato AAAA-MM.'],
    ],
    'attributes' => [],
];

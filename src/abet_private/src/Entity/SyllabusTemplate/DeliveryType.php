<?php

namespace App\Entity\SyllabusTemplate;

enum DeliveryType: string
{
    case InPerson = 'in_person';
    case Hybrid = 'hybrid';
    case Online = 'online';
}

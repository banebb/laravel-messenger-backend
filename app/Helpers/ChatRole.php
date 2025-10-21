<?php

namespace App\Helpers;

enum ChatRole: string
{
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case MEMBER = 'member';
}

<?php
declare(strict_types=1);

enum TickFailurePolicy: string
{
    case STOP = 'stop';
    case CONTINUE = 'continue';
}

<?php

namespace CustomFrontMenu\Service;

enum FilterType: int
{
    const URL = FILTER_SANITIZE_URL;
    const EMAIL = FILTER_SANITIZE_EMAIL;
}
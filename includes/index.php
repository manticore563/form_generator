<?php
/**
 * Includes directory - contains PHP libraries
 * This file prevents directory listing and unauthorized access
 */
http_response_code(403);
exit('Access denied');

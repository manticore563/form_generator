<?php
/**
 * Log directory - contains application logs
 * This file prevents directory listing and unauthorized access
 */
http_response_code(403);
exit('Access denied');

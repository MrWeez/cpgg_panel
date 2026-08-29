<?php

namespace App\Classes;

abstract class AbstractExtension
{
    abstract public static function getConfig(): array;

    /**
     * Get the sidebar pages this extension wants to add.
     *
     * Each entry is an array with the following keys:
     * - title: (string, required) displayed label, wrapped with __() by the sidebar.
     * - icon: (string, optional) FontAwesome icon class, defaults to "fas fa-circle".
     * - route: (string, optional) named route registered by the extension.
     * - route_params: (array, optional) parameters for the named route.
     * - url: (string, optional) absolute path (must start with "/"), used when no route is given.
     * - permissions: (array, optional) permission names; the page is only visible when the
     *   authenticated user has at least one of them. Empty array = visible to everyone.
     * - area: (string, optional) "user" (top sidebar section) or "admin" (Extensions section).
     *   Defaults to "user".
     * - order: (int, optional) sort order within the section. Defaults to 0.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getSidebarPages(): array
    {
        return [];
    }

    /**
     * Get the permissions this extension registers with the role system.
     *
     * The array is keyed by a human readable name with the permission name as the value,
     * e.g. ["View My Extension Page" => "extension.mypage.read"]. These are created in the
     * database by the permission seeder so they can be assigned to roles.
     *
     * @return array<string, string>
     */
    public static function getPermissions(): array
    {
        return [];
    }
}

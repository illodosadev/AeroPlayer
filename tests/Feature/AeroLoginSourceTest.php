<?php

// The Aero login uses no starter-kit (shadcn-vue) component (aero-design-system, spec aero-design, "Aero login page").

test('the login page source imports nothing from the starter-kit ui components', function () {
    expect(file_get_contents(resource_path('js/pages/auth/Login.vue')))->not->toContain('@/components/ui');
});

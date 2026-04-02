<?php

namespace Database\Seeders;

use App\Modules\Core\Models\User;
use App\Modules\Post\Models\Post;
use App\Modules\Post\Models\PostCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * Thứ tự: User → PostCategory (cây) → Post → Permission/Role/Team (phân quyền).
     */
    public function run(): void
    {
        $this->seedUsers();
        $this->seedPostCategories();
        $this->seedPosts();
        $this->call(PermissionSeeder::class);
        $this->call(TaskAssignmentDataSeeder::class);
        $this->call(SettingSeeder::class);
    }

    /**
     * Tạo user. User đầu tiên (id=1) dùng làm người tạo/sửa cho dữ liệu mẫu.
     */
    protected function seedUsers(): void
    {
        User::factory(10)->create();

        // Gán created_by, updated_by (user 1 tự tham chiếu; các user khác tham chiếu user 1)
        User::where('id', 1)->update(['created_by' => 1, 'updated_by' => 1]);
        User::where('id', '>', 1)->update(['created_by' => 1, 'updated_by' => 1]);
    }

    /**
     * Tạo danh mục tin tức dạng cây (parent_id): vài danh mục gốc, mỗi gốc có vài danh mục con.
     */
    protected function seedPostCategories(): void
    {
        $user = User::first();
        if (! $user) {
            return;
        }

        $rootNames = ['Tin công nghệ', 'Tin thể thao', 'Tin kinh tế', 'Giải trí', 'Giáo dục'];
        foreach ($rootNames as $index => $name) {
            PostCategory::factory()
                ->create([
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'sort_order' => $index + 1,
                    'parent_id' => null,
                ]);
        }

        $roots = PostCategory::whereNull('parent_id')->orderBy('sort_order')->get();

        foreach ($roots as $root) {
            $childCount = rand(2, 3);
            for ($i = 0; $i < $childCount; $i++) {
                PostCategory::factory()->create([
                    'name' => $root->name.' - '.fake()->word(),
                    'slug' => \Illuminate\Support\Str::slug($root->name.' '.fake()->word()).'-'.uniqid(),
                    'sort_order' => $i + 1,
                    'parent_id' => $root->id,
                ]);
            }
        }

        PostCategory::whereNull('created_by')->update([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    /**
     * Tạo bài viết, gán ngẫu nhiên user và danh mục.
     */
    protected function seedPosts(): void
    {
        $users = User::all();
        $categories = PostCategory::all();

        if ($users->isEmpty()) {
            return;
        }

        Post::withoutEvents(function () use ($users, $categories) {
            Post::factory(20)
                ->sequence(
                    fn ($sequence) => [
                        'created_by' => $users->random()->id,
                        'updated_by' => $users->random()->id,
                    ]
                )
                ->create()
                ->each(function (Post $post) use ($categories) {
                    if ($categories->isNotEmpty()) {
                        $post->categories()->sync([$categories->random()->id]);
                    }
                });
        });
    }
}

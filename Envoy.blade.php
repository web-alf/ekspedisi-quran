@servers(['web' => 'root@45.77.42.220'])

@setup
    // Mengatur repository, direktori rilis, dan variabel lainnya yang dibutuhkan untuk deployment
    $repository         = 'git@github.com:agoesset/ekspedisi-quran.git';
    $releases_dir       = '/var/www/app/releases';
    $app_dir            = '/var/www/app';
    $release            = date('YmdHis'); // Membuat timestamp untuk menandai rilis baru
    $new_release_dir    = $releases_dir .'/'. $release;
@endsetup

@story('deploy')
    {{-- Daftar tugas yang akan dijalankan secara berurutan selama proses deployment --}}
    clone_repository
    run_composer
    create_cache_directory
    link_env_file
    generate_app_key
    handle_storage_directory
    backup_database
    run_migrations
    run_optimize
    update_symlinks
    restart_queues
    delete_git_metadata
    clean_old_releases
    change_permission_owner
    restart_php
    notify_deployment
@endstory

@story('deploy:seed')
    {{-- Deployment lengkap termasuk seeder untuk first install atau update data master --}}
    clone_repository
    run_composer
    create_cache_directory
    link_env_file
    generate_app_key
    handle_storage_directory
    backup_database
    run_migrations
    run_seeders
    run_optimize
    update_symlinks
    restart_queues
    delete_git_metadata
    clean_old_releases
    change_permission_owner
    restart_php
    notify_deployment
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    {{-- Membuat direktori rilis jika belum ada, kemudian melakukan clone dari repository git --}}
    [ -d {{ $releases_dir }} ] || mkdir {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
@endtask

@task('run_composer')
    {{-- Memulai proses deployment dan menjalankan composer untuk menginstal dependensi --}}
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    echo "Running composer..."
    composer install --optimize-autoloader
@endtask

@task('create_cache_directory')
    {{-- Membuat direktori cache dan bootstrap serta memastikan direktori lain yang diperlukan tersedia dan memiliki izin yang benar --}}
    echo 'Ensuring bootstrap and cache directories exist'
    mkdir -p {{ $new_release_dir }}/bootstrap/cache
    chown -R www-data:www-data {{ $new_release_dir }}/bootstrap/cache
    chmod -R 775 {{ $new_release_dir }}/bootstrap/cache

    echo 'Ensuring other necessary directories exist and writable'
    mkdir -p {{ $new_release_dir }}/storage/framework/views
    mkdir -p {{ $new_release_dir }}/storage/framework/sessions
    mkdir -p {{ $new_release_dir }}/storage/framework/cache
    chown -R www-data:www-data {{ $new_release_dir }}/storage
    chmod -R 775 {{ $new_release_dir }}/storage
@endtask

@task('link_env_file')
    {{-- Menautkan file .env dari direktori aplikasi utama ke rilis baru --}}
    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env
@endtask

@task('generate_app_key')
    {{-- Mengecek apakah APP_KEY sudah ada, jika tidak, maka akan di-generate secara otomatis --}}
    echo 'Checking for existing application key'

    if ! grep -q '^APP_KEY=' {{ $app_dir }}/.env; then
        echo 'Generating application key'
        cd {{ $app_dir }}/current
        php artisan key:generate
    else
        echo 'Application key already exists, skipping key generation'
    fi
@endtask

@task('handle_storage_directory')
    {{-- Mengelola direktori storage, memastikan kontennya tetap terjaga atau membuat yang baru jika belum ada --}}
    echo 'Handling storage directory'
    if [ ! -d {{ $app_dir }}/storage ]; then
        echo 'Creating storage directory in app_dir'
        cp -r {{ $new_release_dir }}/storage {{ $app_dir }}/storage
    else
        echo 'Preserving existing storage contents'
        rsync -a --delete {{ $app_dir }}/storage/ {{ $new_release_dir }}/storage/
    fi
    chown -R www-data:www-data {{ $app_dir }}/storage
    chmod -R 775 {{ $app_dir }}/storage
@endtask

@task('run_migrations')
    {{-- Menjalankan migrasi database untuk memperbarui skema --}}
    echo 'Running migrations'
    cd {{ $new_release_dir }}
    php artisan migrate --force
@endtask

@task('run_seeders')
    {{-- Menjalankan semua seeder untuk memastikan data tersedia (idempotent dengan firstOrCreate) --}}
    echo 'Running all seeders'
    cd {{ $new_release_dir }}
    php artisan db:seed --force
@endtask

@task('run_optimize')
    {{-- Menjalankan perintah optimasi untuk membersihkan cache dan mempercepat aplikasi --}}
    echo 'Running optimization commands'
    cd {{ $new_release_dir }}
    php artisan optimize:clear
@endtask

@task('update_symlinks')
    {{-- Menautkan direktori storage dan merilis versi terbaru sebagai 'current' --}}
    echo 'Linking storage directory'
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current

    echo 'Linking storage:link'
    php {{ $new_release_dir }}/artisan storage:link
@endtask

@task('delete_git_metadata')
    {{-- Menghapus direktori .git setelah clone repository untuk menjaga kebersihan kode --}}
    echo 'Delete .git folder'
    cd {{ $new_release_dir }}
    rm -rf .git
@endtask

@task('change_permission_owner')
    {{-- Mengubah pemilik izin file menjadi user dan group www-data --}}
    echo 'Change Permission Owner'
    cd {{ $new_release_dir }}
    chown -R www-data:www-data .
@endtask

@task('clean_old_releases')
    {{-- Menghapus rilis lama, hanya menyimpan 2 rilis terbaru untuk efisiensi ruang --}}
    purging=$(ls -dt {{ $releases_dir }}/* | tail -n +3);

    if [ "$purging" != "" ]; then
        echo Purging old releases: $purging;
        rm -rf $purging;
    else
        echo 'No releases found for purging at this time';
    fi
@endtask

@task('restart_php')
    {{-- Merestart service PHP-FPM agar rilis baru langsung aktif --}}
    echo 'Restarting php8.3-fpm'
    sudo systemctl restart php8.3-fpm
@endtask

@task('backup_database')
    {{-- Backup database sebelum migrasi untuk keamanan --}}
    echo 'Creating database backup...'
    BACKUP_DIR="{{ $app_dir }}/backups"
    BACKUP_FILE="backup_{{ $release }}.sql"

    # Buat direktori backup jika belum ada
    mkdir -p $BACKUP_DIR

    # Ambil database credentials dari .env
    DB_DATABASE=$(grep '^DB_DATABASE=' {{ $app_dir }}/.env | cut -d '=' -f2)
    DB_USERNAME=$(grep '^DB_USERNAME=' {{ $app_dir }}/.env | cut -d '=' -f2)
    DB_PASSWORD=$(grep '^DB_PASSWORD=' {{ $app_dir }}/.env | cut -d '=' -f2)
    DB_HOST=$(grep '^DB_HOST=' {{ $app_dir }}/.env | cut -d '=' -f2 || echo 'localhost')

    if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
        echo '⚠️  Warning: Database credentials not found, skipping backup'
        exit 0
    fi

    # Create backup
    if mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_DIR/$BACKUP_FILE" 2>/dev/null; then
        echo "✅ Database backup created: $BACKUP_FILE"
        # Compress backup
        gzip "$BACKUP_DIR/$BACKUP_FILE"
        echo "✅ Backup compressed: ${BACKUP_FILE}.gz"

        # Keep only last 10 backups
        ls -t "$BACKUP_DIR"/backup_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm
        echo '🧹 Old backups cleaned (keeping last 10)'
    else
        echo '⚠️  Warning: Database backup failed, continuing deployment...'
    fi
@endtask

@task('restart_queues')
    {{-- Restart queue workers untuk mengambil kode baru --}}
    echo 'Restarting queue workers...'

    # Cek apakah supervisor terinstall
    if command -v supervisorctl &> /dev/null; then
        sudo supervisorctl restart all || echo '⚠️  Some queue workers may need manual restart'
        echo '✅ Queue workers restarted via Supervisor'
    else
        echo '⚠️  Supervisor not found, skipping queue restart'
        echo '    Make sure to restart queue workers manually if using them'
    fi
@endtask

@task('notify_deployment')
    {{-- Notifikasi deployment selesai (placeholder untuk Slack/Discord/email) --}}
    echo 'Sending deployment notification...'

    RELEASE=$(basename {{ $new_release_dir }})

    # Log deployment
    echo "🚀 Deployment Successful!"
    echo "   Release: $RELEASE"
    echo "   Time: $(date)"
    echo "   Server: {{ '@web' }}"

    # Placeholder untuk integrasi notifikasi
    # Uncomment dan sesuaikan jika ingin menggunakan Slack/Discord

    # Slack notification (requires SLACK_WEBHOOK_URL in .env)
    # SLACK_URL=$(grep '^SLACK_WEBHOOK_URL=' {{ $app_dir }}/.env | cut -d '=' -f2)
    # if [ -n "$SLACK_URL" ]; then
    #     curl -X POST -H 'Content-type: application/json' \
    #         --data "{\"text\":\"🚀 Deployment successful! Release: $RELEASE\"}" \
    #         "$SLACK_URL" > /dev/null 2>&1
    # fi
@endtask

@story('deploy:quick')
    {{-- Deploy cepat tanpa backup (hanya untuk hotfix minor) --}}
    clone_repository
    run_composer
    link_env_file
    run_optimize
    update_symlinks
    restart_queues
    delete_git_metadata
    clean_old_releases
    change_permission_owner
    restart_php
@endstory

<!-- rollback -->
@task('rollback')
    echo "⚠️  Starting rollback process"
    cd {{ $app_dir }}

    # Cek apakah ada symlink current
    if [ ! -L {{ $app_dir }}/current ]; then
        echo "❌ No current release found. Rollback aborted."
        exit 1
    fi

    # Ambil rilis saat ini
    current_release=$(readlink -f {{ $app_dir }}/current)
    echo "Current release: $(basename $current_release)"

    # Ambil rilis sebelumnya
    previous_release=$(ls -dt {{ $releases_dir }}/* | sed -n '2p')

    if [ -z "$previous_release" ]; then
        echo "❌ No previous release found. Rollback aborted."
        exit 1
    fi

    echo "↩️  Rolling back to: $(basename $previous_release)"

    # Hapus symlink current
    rm {{ $app_dir }}/current

    # Buat symlink ke rilis sebelumnya
    ln -s $previous_release {{ $app_dir }}/current

    # Pindah ke rilis sebelumnya
    cd $previous_release

    # Bersihkan cache
    echo "Clearing application cache"
    php artisan cache:clear
    php artisan config:clear
    php artisan view:clear

    # Restart PHP-FPM
    echo "Restarting PHP-FPM"
    sudo systemctl restart php8.3-fpm

    # Restart queues
    echo "Restarting queue workers"
    if command -v supervisorctl &> /dev/null; then
        sudo supervisorctl restart all || true
    fi

    # Health check setelah rollback
    echo "Running health check..."
    sleep 2
    HTTP_STATUS=$(curl -s -L -o /dev/null -w "%{http_code}" http://localhost/up 2>/dev/null || echo '000')

    if [ "$HTTP_STATUS" = "200" ]; then
        echo "✅ Health check passed after rollback"
    else
        echo "⚠️  Health check returned HTTP $HTTP_STATUS, please verify manually"
    fi

    # Ambil rilis terakhir
    latest_release=$(ls -dt {{ $releases_dir }}/* | head -n 1)
    failed_release=$(basename $current_release)

    if [ -n "$latest_release" ] && [ "$latest_release" != "$previous_release" ]; then
        echo "🗑️  Removing failed release: $failed_release"
        rm -rf $current_release
    fi

    # Log rollback
    echo "⚠️  Rollback completed at $(date) - From: $failed_release - To: $(basename $previous_release)" >> {{ $app_dir }}/deployment.log

    echo "✅ Rollback completed successfully"
@endtask

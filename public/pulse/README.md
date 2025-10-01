# Pulse Display System

The Pulse Display System is a digital bulletin board that displays rotating slides and footer messages for office communication.

## Files Structure

- `index.html` - Main display interface
- `slides.json` - Configuration for all slides
- `footer-messages.json` - Configuration for footer messages
- `images/` - Directory for image assets
- `videos/` - Directory for video assets
- `backups/` - Automatic backups of JSON files

## Admin Interface

Access the admin interface at `/pulse-admin.php` (admin role required).

### Features

- **Slides Management**: Add, edit, delete, and reorder slides
- **Footer Messages Management**: Add, edit, delete, and reorder footer messages
- **JSON Editor**: Direct editing of JSON files with validation
- **Statistics**: View slide counts, durations, and message statistics
- **Automatic Backups**: JSON files are backed up before changes
- **Live Preview**: Preview the display while making changes

### Slide Types

1. **Content Slides**: Text-based slides with title, subtitle, body, and lists
2. **Iframe Slides**: Embed external web content
3. **Video Slides**: Display local video files
4. **Image Slides**: Display static images

### Slide Configuration

Each slide requires:
- `type`: "content", "iframe", "video", or "image"
- `duration`: Display time in seconds
- `id`: Unique identifier

Type-specific fields:
- **Content**: `content` object with `title`, `subtitle`, `body`, `list`
- **Iframe/Video/Image**: `url`, `title`, and `alt` (for images)

### Footer Messages

Simple array of strings that rotate every 30 seconds.

## Usage

1. Access the admin panel from the main dashboard
2. Use the tabs to switch between slides and messages management
3. Add, edit, or reorder content as needed
4. Save changes to update the display
5. Use the preview link to see changes in real-time

## Backup System

The system automatically creates timestamped backups in the `backups/` directory before saving changes. This ensures you can recover previous configurations if needed.

## Security

- Admin role required for access
- JSON validation prevents malformed data
- Automatic backups protect against data loss
- Input sanitization prevents XSS attacks
const fs = require('fs');
const http = require('http');
const path = require('path');

const root = path.resolve(__dirname, '..');
const webdavOrigin = process.env.TSML_UI_WEBDAV_ORIGIN || 'http://localhost:8081';
const files = process.argv.includes('--unminified')
  ? [['tools/tsml-ui/public/app.js', 'assets/js/unminified_app.js']]
  : [
      ['tools/tsml-ui/public/app.js', 'assets/js/app.js'],
      ['tools/tsml-ui/public/app.js.map', 'assets/js/app.js.map'],
    ];

function webdavUrl(destination) {
  const marker = `${path.sep}wp-content${path.sep}`;
  const index = destination.indexOf(marker);
  if (index === -1) {
    throw new Error(`Cannot derive WebDAV URL from ${destination}`);
  }

  return `${webdavOrigin}/${destination
    .slice(index + 1)
    .split(path.sep)
    .map(encodeURIComponent)
    .join('/')}`;
}

function putFile(source, destination) {
  return new Promise((resolve, reject) => {
    const url = new URL(webdavUrl(destination));
    const request = http.request(
      url,
      {
        method: 'PUT',
        headers: {
          'Content-Length': fs.statSync(source).size,
        },
      },
      (response) => {
        response.resume();
        response.on('end', () => {
          if (response.statusCode >= 200 && response.statusCode < 300) {
            resolve();
            return;
          }

          reject(new Error(`WebDAV PUT failed for ${url.href}: ${response.statusCode}`));
        });
      }
    );

    request.on('error', reject);
    fs.createReadStream(source).pipe(request);
  });
}

async function copyFile(sourceRelative, destinationRelative) {
  const source = path.join(root, sourceRelative);
  const destination = path.join(root, destinationRelative);

  if (!fs.existsSync(source)) {
    console.log(`Skipping missing ${sourceRelative}`);
    return;
  }

  try {
    fs.copyFileSync(source, destination);
  } catch (error) {
    if (error.code !== 'EPERM' && error.code !== 'EACCES') {
      throw error;
    }

    await putFile(source, destination);
  }

  console.log(`Copied ${sourceRelative} to ${destinationRelative}`);
}

Promise.all(files.map(([source, destination]) => copyFile(source, destination))).catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

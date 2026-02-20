const fs = require('fs');
const postcss = require('postcss');
const prefixSelector = require('postcss-prefix-selector');

const css = fs.readFileSync('src/styles/user-dashboard.css', 'utf8');

const addImportant = postcss.plugin('add-important', () => {
    return (root) => {
        root.walkDecls(decl => {
            decl.important = true;
        });
    };
});

postcss([
    prefixSelector({
        prefix: '#seamless-user-dashboard-section',
        exclude: ['@keyframes', 'from', 'to', /^\d+%$/]
    }),
    addImportant()
])
.process(css, { from: 'src/styles/user-dashboard.css', to: 'src/styles/user-dashboard.css' })
.then(result => {
    fs.writeFileSync('src/styles/user-dashboard.css', result.css);
    console.log('CSS updated successfully!');
});

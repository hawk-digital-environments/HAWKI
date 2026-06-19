import postcssGlobalData from '@csstools/postcss-global-data';
import postcssCustomMedia from 'postcss-custom-media';

export default {
    plugins: [
        postcssGlobalData({
            files: ['resources/css/tokens/breakpoints.css']
        }),
        postcssCustomMedia()
    ]
};

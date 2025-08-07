import { registerBlockType } from '@wordpress/blocks';
import Edit from './editor';
import metadata from './block.json';

registerBlockType(metadata.name, {
    edit: Edit,
    save: () => null, // Dynamic block, rendered on the server
});

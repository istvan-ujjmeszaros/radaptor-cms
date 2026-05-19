export const SUPPORTED_FORM_VALIDATOR_TYPES = [
	"required",
	"email",
	"url",
	"min_length",
	"max_length",
	"number_min",
	"number_max",
	"regex",
	"enum",
	"date",
	"file_type",
	"file_size",
];

export const formValidatorRegistry = Object.freeze(
	Object.fromEntries(SUPPORTED_FORM_VALIDATOR_TYPES.map((type) => [type, true])),
);
